<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Setting;
use App\Models\VatRate;
use XMLWriter;

/**
 * Verkoopfactuur als UBL 2.1-bestand volgens de structuur van Peppol BIS Billing 3.0 (EN 16931).
 * Niet gecertificeerd en niet met de officiële Peppol-validator getoetst: controleer een eerste bestand
 * bij je boekhoudpakket of ontvanger. Creditfacturen worden een CreditNote met positieve bedragen.
 */
final class Ubl
{
    private const CUSTOMIZATION = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    private const PROFILE = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /** Eenheden in Werkbank → UN/ECE Rec. 20 */
    private const UNITS = ['uur' => 'HUR', 'uren' => 'HUR', 'u' => 'HUR', 'dag' => 'DAY', 'dagen' => 'DAY', 'week' => 'WEE', 'maand' => 'MON',
        'jaar' => 'ANN', 'km' => 'KMT', 'm' => 'MTR', 'meter' => 'MTR', 'm2' => 'MTK', 'm²' => 'MTK', 'm3' => 'MTQ', 'm³' => 'MTQ',
        'kg' => 'KGM', 'liter' => 'LTR', 'l' => 'LTR', 'kwh' => 'KWH', 'set' => 'SET'];

    /** @return array{xml: string, filename: string, warnings: list<string>} */
    public static function invoice(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines.unit', 'company', 'contact']);
        $credit = $invoice->isCredit();
        $sign = $credit ? -1 : 1;
        $root = $credit ? 'CreditNote' : 'Invoice';
        $rates = VatRate::query()->get()->keyBy('id');
        $warnings = [];
        $totals = $invoice->computeTotals();

        $w = new XMLWriter;
        $w->openMemory();
        $w->setIndent(true);
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement($root);
        $w->writeAttribute('xmlns', "urn:oasis:names:specification:ubl:schema:xsd:{$root}-2");
        $w->writeAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $w->writeAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        self::el($w, 'cbc:CustomizationID', self::CUSTOMIZATION);
        self::el($w, 'cbc:ProfileID', self::PROFILE);
        self::el($w, 'cbc:ID', (string) $invoice->number);
        self::el($w, 'cbc:IssueDate', $invoice->date?->toDateString());
        if (! $credit) {
            self::el($w, 'cbc:DueDate', $invoice->due_date?->toDateString());
        }
        self::el($w, $credit ? 'cbc:CreditNoteTypeCode' : 'cbc:InvoiceTypeCode', $credit ? '381' : '380');
        if ($invoice->subject) {
            self::el($w, 'cbc:Note', mb_substr($invoice->subject, 0, 1000));
        }
        self::el($w, 'cbc:DocumentCurrencyCode', 'EUR');
        // Peppol vereist een kopersreferentie of ordernummer.
        self::el($w, 'cbc:BuyerReference', mb_substr($invoice->client_reference ?: (string) ($invoice->company?->number ?? $invoice->number), 0, 200));
        if ($credit && $invoice->credit_of_id && ($original = Invoice::find($invoice->credit_of_id))) {
            $w->startElement('cac:BillingReference');
            $w->startElement('cac:InvoiceDocumentReference');
            self::el($w, 'cbc:ID', (string) $original->number);
            self::el($w, 'cbc:IssueDate', $original->date?->toDateString());
            $w->endElement();
            $w->endElement();
        }

        self::supplier($w, $warnings);
        self::customer($w, $invoice->company, $invoice->contact?->name, $warnings);

        // Betaalinstructie: overboeking (58 = SEPA credit transfer) met IBAN; bij incasso 59.
        $iban = Iban::normalize((string) Setting::get('bedrijf.iban'));
        $w->startElement('cac:PaymentMeans');
        self::el($w, 'cbc:PaymentMeansCode', $invoice->payment_method === 'incasso' ? '59' : '58');
        self::el($w, 'cbc:PaymentID', (string) $invoice->number);
        if ($invoice->payment_method !== 'incasso') {
            if (Iban::valid($iban)) {
                $w->startElement('cac:PayeeFinancialAccount');
                self::el($w, 'cbc:ID', $iban);
                self::el($w, 'cbc:Name', mb_substr((string) Setting::get('bedrijf.naam'), 0, 70));
                $w->endElement();
            } else {
                $warnings[] = 'Geen geldig IBAN in de bedrijfsgegevens (Beheer › Instellingen).';
            }
        }
        $w->endElement();
        if ($invoice->due_date && ! $credit) {
            $w->startElement('cac:PaymentTerms');
            self::el($w, 'cbc:Note', 'Betalen vóór '.$invoice->due_date->format('d-m-Y').' onder vermelding van factuurnummer '.$invoice->number.'.');
            $w->endElement();
        }

        // Btw per tarief
        $w->startElement('cac:TaxTotal');
        self::amount($w, 'cbc:TaxAmount', $sign * (float) $totals['vat']);
        foreach ($totals['per_rate'] as $rateId => $r) {
            [$cat, $pct, $reason] = self::category($rates[$rateId] ?? null, $r['reverse_charge']);
            $w->startElement('cac:TaxSubtotal');
            self::amount($w, 'cbc:TaxableAmount', $sign * (float) $r['base']);
            self::amount($w, 'cbc:TaxAmount', $r['reverse_charge'] ? 0 : $sign * (float) $r['vat']);
            self::taxCategory($w, 'cac:TaxCategory', $cat, $pct, $reason);
            $w->endElement();
        }
        $w->endElement();

        $excl = $sign * (float) $totals['excl'];
        $incl = $sign * (float) $totals['incl'];
        $paid = $sign * (float) $invoice->total_paid;
        $w->startElement('cac:LegalMonetaryTotal');
        self::amount($w, 'cbc:LineExtensionAmount', $excl);
        self::amount($w, 'cbc:TaxExclusiveAmount', $excl);
        self::amount($w, 'cbc:TaxInclusiveAmount', $incl);
        if (abs($paid) > 0.004) {
            self::amount($w, 'cbc:PrepaidAmount', $paid);
        }
        self::amount($w, 'cbc:PayableAmount', $incl - $paid);
        $w->endElement();

        $n = 0;
        foreach ($invoice->lines as $line) {
            if ($line->row_type !== 'normaal') {
                continue;
            }
            $n++;
            [$cat, $pct] = self::category($rates[$line->vat_rate_id] ?? null, (bool) ($rates[$line->vat_rate_id]->reverse_charge ?? false));
            $qty = $sign * (float) $line->quantity;
            $gross = (float) $line->unit_price;
            $net = $gross * (1 - (float) $line->discount / 100);
            // Nettoprijs × aantal = regelbedrag; bij een negatieve prijs (korting als regel) blijft de prijs positief.
            if ($net < 0) {
                $net = -$net;
                $gross = -$gross;
                $qty = -$qty;
            }
            $w->startElement($credit ? 'cac:CreditNoteLine' : 'cac:InvoiceLine');
            self::el($w, 'cbc:ID', (string) $n);
            $w->startElement($credit ? 'cbc:CreditedQuantity' : 'cbc:InvoicedQuantity');
            $w->writeAttribute('unitCode', self::unit($line->unit?->name));
            $w->text(self::num($qty, 4));
            $w->endElement();
            self::amount($w, 'cbc:LineExtensionAmount', $sign * (float) $line->total_excl);
            $w->startElement('cac:Item');
            if ($line->description) {
                self::el($w, 'cbc:Description', mb_substr(strip_tags($line->description), 0, 1000));
            }
            self::el($w, 'cbc:Name', mb_substr($line->title ?: 'Regel '.$n, 0, 200));
            self::taxCategory($w, 'cac:ClassifiedTaxCategory', $cat, $pct, null);
            $w->endElement();
            $w->startElement('cac:Price');
            self::amount($w, 'cbc:PriceAmount', $net, 6);
            if ((float) $line->discount != 0.0) {
                $w->startElement('cac:AllowanceCharge');
                self::el($w, 'cbc:ChargeIndicator', 'false');
                self::amount($w, 'cbc:Amount', abs($gross) - $net, 6);
                self::amount($w, 'cbc:BaseAmount', abs($gross), 6);
                $w->endElement();
            }
            $w->endElement();
            $w->endElement();
        }
        if ($n === 0) {
            $warnings[] = 'De factuur heeft geen regels.';
        }

        $w->endElement();
        $w->endDocument();

        return ['xml' => $w->outputMemory(), 'filename' => ($credit ? 'creditfactuur-' : 'factuur-').$invoice->number.'.xml', 'warnings' => $warnings];
    }

    /** [categorie, percentage, vrijstellingsreden] volgens UNCL5305. */
    private static function category(?VatRate $rate, bool $reverse): array
    {
        $name = mb_strtolower((string) $rate?->name);
        if ($reverse) {
            return match (true) {
                str_contains($name, 'binnen eu') => ['K', 0, 'Intracommunautaire levering'],
                str_contains($name, 'buiten eu') || str_contains($name, 'export') => ['G', 0, 'Export buiten de EU'],
                default => ['AE', 0, 'Btw verlegd'],
            };
        }
        if (! $rate || (float) $rate->percentage == 0.0) {
            return str_contains($name, 'vrij') ? ['E', 0, 'Vrijgesteld van btw'] : ['Z', 0, null];
        }

        return ['S', (float) $rate->percentage, null];
    }

    private static function taxCategory(XMLWriter $w, string $element, string $cat, float $pct, ?string $reason): void
    {
        $w->startElement($element);
        self::el($w, 'cbc:ID', $cat);
        self::el($w, 'cbc:Percent', self::num($pct, 2));
        if ($reason && in_array($cat, ['AE', 'K', 'G', 'E'], true)) {
            self::el($w, 'cbc:TaxExemptionReason', $reason);
        }
        $w->startElement('cac:TaxScheme');
        self::el($w, 'cbc:ID', 'VAT');
        $w->endElement();
        $w->endElement();
    }

    private static function supplier(XMLWriter $w, array &$warnings): void
    {
        $name = (string) Setting::get('bedrijf.naam');
        $kvk = preg_replace('/\D/', '', (string) Setting::get('bedrijf.kvk'));
        $vat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) Setting::get('bedrijf.btw')));
        if ($name === '') {
            $warnings[] = 'Bedrijfsnaam ontbreekt (Beheer › Instellingen).';
        }
        $w->startElement('cac:AccountingSupplierParty');
        $w->startElement('cac:Party');
        self::endpoint($w, $kvk, $vat, $warnings, 'eigen bedrijf');
        self::address($w, (string) Setting::get('bedrijf.adres'), null, (string) Setting::get('bedrijf.postcode'), (string) Setting::get('bedrijf.plaats'), (string) Setting::get('bedrijf.land'));
        if ($vat !== '') {
            $w->startElement('cac:PartyTaxScheme');
            self::el($w, 'cbc:CompanyID', $vat);
            $w->startElement('cac:TaxScheme');
            self::el($w, 'cbc:ID', 'VAT');
            $w->endElement();
            $w->endElement();
        } else {
            $warnings[] = 'Btw-nummer van het eigen bedrijf ontbreekt (Beheer › Instellingen).';
        }
        $w->startElement('cac:PartyLegalEntity');
        self::el($w, 'cbc:RegistrationName', $name ?: config('app.name'));
        if ($kvk !== '') {
            $w->startElement('cbc:CompanyID');
            $w->writeAttribute('schemeID', '0106');
            $w->text($kvk);
            $w->endElement();
        }
        $w->endElement();
        if ($email = Setting::get('bedrijf.email')) {
            $w->startElement('cac:Contact');
            self::el($w, 'cbc:ElectronicMail', $email);
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
    }

    private static function customer(XMLWriter $w, ?Company $c, ?string $contact, array &$warnings): void
    {
        if (! $c) {
            $warnings[] = 'De factuur heeft geen relatie.';
        }
        $kvk = preg_replace('/\D/', '', (string) $c?->coc_number);
        $vat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $c?->vat_number));
        $invoiceAddress = $c && ($c->invoice_street || $c->invoice_city);
        $p = $invoiceAddress ? 'invoice' : 'visit';
        $w->startElement('cac:AccountingCustomerParty');
        $w->startElement('cac:Party');
        self::endpoint($w, $kvk, $vat, $warnings, 'relatie');
        self::address($w, (string) $c?->{"{$p}_street"}, $c?->{"{$p}_house_number"}, (string) $c?->{"{$p}_postcode"}, (string) $c?->{"{$p}_city"}, (string) $c?->{"{$p}_country"});
        if ($vat !== '') {
            $w->startElement('cac:PartyTaxScheme');
            self::el($w, 'cbc:CompanyID', $vat);
            $w->startElement('cac:TaxScheme');
            self::el($w, 'cbc:ID', 'VAT');
            $w->endElement();
            $w->endElement();
        }
        $w->startElement('cac:PartyLegalEntity');
        self::el($w, 'cbc:RegistrationName', mb_substr(($invoiceAddress && $c->invoice_company_name) ? $c->invoice_company_name : ($c?->legal_name ?: $c?->name ?: 'Onbekend'), 0, 200));
        if ($kvk !== '') {
            $w->startElement('cbc:CompanyID');
            $w->writeAttribute('schemeID', '0106');
            $w->text($kvk);
            $w->endElement();
        }
        $w->endElement();
        $mail = $c?->invoice_email ?: $c?->email;
        if ($contact || $mail) {
            $w->startElement('cac:Contact');
            if ($contact || $c?->invoice_attention) {
                self::el($w, 'cbc:Name', mb_substr($c?->invoice_attention ?: $contact, 0, 200));
            }
            if ($mail) {
                self::el($w, 'cbc:ElectronicMail', $mail);
            }
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
    }

    /** Peppol-adres: KvK (0106) of, voor Nederland, het btw-nummer (9944). */
    private static function endpoint(XMLWriter $w, string $kvk, string $vat, array &$warnings, string $who): void
    {
        [$scheme, $id] = match (true) {
            strlen($kvk) === 8 => ['0106', $kvk],
            str_starts_with($vat, 'NL') => ['9944', $vat],
            default => [null, null],
        };
        if (! $scheme) {
            $warnings[] = "Geen KvK- of Nederlands btw-nummer bij {$who}: het bestand heeft geen Peppol-adres (EndpointID).";

            return;
        }
        $w->startElement('cbc:EndpointID');
        $w->writeAttribute('schemeID', $scheme);
        $w->text($id);
        $w->endElement();
    }

    private static function address(XMLWriter $w, string $street, ?string $number, string $postcode, string $city, string $country): void
    {
        $w->startElement('cac:PostalAddress');
        if (trim($street.' '.$number) !== '') {
            self::el($w, 'cbc:StreetName', trim($street.' '.$number));
        }
        if ($city !== '') {
            self::el($w, 'cbc:CityName', $city);
        }
        if ($postcode !== '') {
            self::el($w, 'cbc:PostalZone', $postcode);
        }
        $w->startElement('cac:Country');
        self::el($w, 'cbc:IdentificationCode', self::countryCode($country));
        $w->endElement();
        $w->endElement();
    }

    private static function countryCode(string $country): string
    {
        $c = mb_strtolower(trim($country));
        $map = ['' => 'NL', 'nederland' => 'NL', 'the netherlands' => 'NL', 'netherlands' => 'NL', 'belgië' => 'BE', 'belgie' => 'BE', 'belgium' => 'BE',
            'duitsland' => 'DE', 'germany' => 'DE', 'deutschland' => 'DE', 'frankrijk' => 'FR', 'france' => 'FR', 'luxemburg' => 'LU',
            'verenigd koninkrijk' => 'GB', 'united kingdom' => 'GB', 'spanje' => 'ES', 'italië' => 'IT', 'italie' => 'IT', 'oostenrijk' => 'AT',
            'denemarken' => 'DK', 'zweden' => 'SE', 'polen' => 'PL', 'zwitserland' => 'CH', 'noorwegen' => 'NO', 'ierland' => 'IE', 'portugal' => 'PT'];

        return $map[$c] ?? (preg_match('/^[a-z]{2}$/', $c) ? strtoupper($c) : 'NL');
    }

    private static function unit(?string $name): string
    {
        return self::UNITS[mb_strtolower(trim((string) $name))] ?? 'C62';
    }

    private static function el(XMLWriter $w, string $name, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $w->writeElement($name, $value);
        }
    }

    private static function amount(XMLWriter $w, string $name, float $value, int $decimals = 2): void
    {
        $w->startElement($name);
        $w->writeAttribute('currencyID', 'EUR');
        $w->text(self::num($value, $decimals));
        $w->endElement();
    }

    private static function num(float $n, int $decimals): string
    {
        $s = number_format(round($n, $decimals), $decimals, '.', '');
        if ($decimals > 2) {
            $s = rtrim(rtrim($s, '0'), '.');
            $s = str_contains($s, '.') && strlen(substr(strrchr($s, '.'), 1)) < 2 ? $s.'0' : $s;
        }

        return $s === '-0.00' ? '0.00' : $s;
    }
}
