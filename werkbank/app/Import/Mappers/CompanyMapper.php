<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class CompanyMapper extends Mapper
{
    public function entity(): string
    {
        return 'company';
    }

    public function model(): string
    {
        return Company::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $roles = array_map(fn ($r) => mb_strtolower((string) Gripp::label($r)), (array) ($row['companyroles'] ?? []));
        $has = fn (string ...$c) => (bool) array_intersect($roles, $c);
        $s = fn ($k, $max = 255) => Gripp::str($row[$k] ?? null, $max);

        $attrs = [
            'number' => is_numeric($row['customernumber'] ?? null) ? (int) $row['customernumber'] : null,
            'relation_type' => Gripp::is($row['relationtype'] ?? null, 'particulier', 'privateperson', 'persoon') ? 'particulier' : 'bedrijf',
            'name' => $s('companyname') ?? $s('searchname') ?? trim(($row['firstname'] ?? '').' '.($row['lastname'] ?? '')) ?: 'Relatie '.$row['id'],
            'legal_name' => $s('legalname'),
            'is_customer' => $has('klant', 'customer'),
            'is_supplier' => $has('leverancier', 'supplier'),
            'is_prospect' => $has('prospect'),
            'is_lead' => $has('lead'),
            'email' => $s('email'),
            'invoice_email' => $s('invoiceemail'),
            'phone' => $s('phone', 40),
            'mobile' => $s('mobile', 40),
            'website' => $s('website'),
            'coc_number' => $s('cocnumber', 20),
            'vat_number' => $s('vatnumber', 30),
            'vat_shifted' => Gripp::bool($row['vatshifted'] ?? false),
            'iban' => $s('bankaccount', 40),
            'bic' => $s('bic', 15),
            'bank_account_holder' => $s('bankascription'),
            'invoice_company_name' => $s('invoiceaddress_companyname'),
            'invoice_attention' => $s('invoiceaddress_attn'),
            'invoice_send_by' => Gripp::is($row['invoicesendby'] ?? null, 'post', 'mail') ? 'post' : 'email',
            'payment_method' => $this->paymentMethod($row['paymentmethod'] ?? null),
            'supplier_payment_term_days' => is_numeric($row['termofpayment_purchaseinvoice'] ?? null) ? (int) $row['termofpayment_purchaseinvoice'] : null,
            'founded_on' => Gripp::day($row['foundationdate'] ?? null),
            'account_manager_id' => $ctx->id(Employee::class, $row['accountmanager'] ?? null),
            'notes' => Gripp::text($row['notes'] ?? null),
            'active' => Gripp::bool($row['active'] ?? true),
        ];
        foreach (['visit' => 'visitingaddress', 'postal' => 'postaddress', 'invoice' => 'invoiceaddress'] as $to => $from) {
            $attrs["{$to}_street"] = $s("{$from}_street");
            $attrs["{$to}_house_number"] = $s("{$from}_streetnumber", 20);
            $attrs["{$to}_postcode"] = $s("{$from}_zipcode", 15);
            $attrs["{$to}_city"] = $s("{$from}_city", 100);
            $attrs["{$to}_country"] = $s("{$from}_country", 60);
        }

        return $attrs;
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->syncTags($model, $row, $ctx);
        $this->attachFiles($model, $row, $ctx);
    }

    public static function paymentMethod(mixed $v): string
    {
        return match (true) {
            Gripp::is($v, 'automatische incasso', 'incasso', 'directdebit') => 'incasso',
            Gripp::is($v, 'online betaling', 'online') => 'online',
            default => 'overboeking',
        };
    }
}
