# Bankkoppeling via Ponto (zelf realiseren)

Deze handleiding beschrijft hoe je Werkbank zelf aan je bankrekening koppelt via [Ponto](https://myponto.com) van Isabel Group. Ponto is een PSD2-dienst die rekeninginformatie (saldo's en transacties) en betaalopdrachten van meerdere banken via één API beschikbaar maakt.

> **Status:** Werkbank heeft **geen ingebouwde Ponto-koppeling**. Deze handleiding is een bouwplan: wat je in Ponto instelt, welke API-aanroepen je nodig hebt en hoe je de gegevens aan facturen in Werkbank koppelt. De API-details komen uit de openbare Ponto-documentatie (geraadpleegd oktober 2026). Controleer ze voordat je bouwt: Ponto kan zijn API, voorwaarden en prijzen wijzigen.

## Wat levert het op?

| Doel | Hoe | Nu in Werkbank |
|---|---|---|
| Ontvangen betalingen automatisch bij verkoopfacturen boeken | Transacties ophalen (AIS) en koppelen op factuurnummer en bedrag | Handmatig via "Betaling registreren" |
| Betaalde inkoopfacturen automatisch afvinken | Afschrijvingen koppelen op de betalingskenmerken uit het betaalbestand | Handmatig |
| Inkoopfacturen betalen zonder bestand te uploaden | Bulkbetaling via Ponto (PIS), ondertekenen in Ponto | SEPA-bestand downloaden en bij de bank inlezen |
| Actueel banksaldo op het overzicht | Rekeningen ophalen (AIS) | Niet aanwezig |

Begin met **alleen lezen (AIS)**: dat levert het meeste op, met het minste risico. Betalen via Ponto (PIS) is een aparte, latere stap.

## Welk type integratie?

Ponto kent twee soorten integraties:

- **Ponto Connect-integratie:** voor een cloudapplicatie die door veel organisaties wordt gebruikt (multi-tenant). Vereist registratie als ontwikkelaar, een certificaat en een OAuth-autorisatiestroom per klant.
- **Custom integration:** voor software van **één organisatie**, op eigen servers. Dit is wat je nodig hebt voor een zelf gehoste Werkbank.

Deze handleiding gaat uit van een custom integration.

## Voordat je begint

- **Banken:** controleer in het Ponto-dashboard of je bank en rekeningtype ondersteund worden. Volgens Ponto-partners werken lang niet alle rekeningen (spaarrekeningen bijvoorbeeld niet altijd). Betalen kan alleen als de bank betaalopdrachten via PSD2 toestaat (`paymentsEnabled` / `bulkPaymentsEnabled` in de API).
- **Kosten:** Ponto rekent per gekoppelde rekening en per geïnitieerde betaling, maandelijks. Zie [myponto.com/en/pricing](https://myponto.com/en/pricing/). De sandbox is gratis.
- **Dubbele transacties:** koppel je dezelfde rekening ook al via Ponto aan je boekhoudpakket, dan staan de transacties daar en in Werkbank. Dat is geen probleem zolang je ze maar op één plek boekt.
- **Wie bouwt het:** je hebt iemand nodig die PHP/Laravel kan schrijven en een geplande taak (cron of de Laravel-scheduler) kan inrichten.

## Stap 1 — Sandbox en custom integration aanmaken

1. Maak een account aan op [myponto.com](https://myponto.com). Voor de sandbox zijn geen betaalgegevens nodig.
2. Zet in het Ponto-dashboard de schakelaar op **sandbox**.
3. Ga naar **Accounts** en voeg een testrekening toe bij een sandbox-bank. De digipass-respons in de sandbox is altijd `123456`.
4. Ga naar **Integrations** en kies **custom integration**:
   - een duidelijke naam, bijvoorbeeld "Werkbank";
   - scope **AIS** (alleen lezen) of **AIS & PIS** (ook betalen);
   - de rekeningen die Werkbank mag zien. Die kun je later nog wijzigen.
5. Noteer de **client ID** en de **client secret**. Het secret wordt maar één keer getoond; je kunt het wel opnieuw genereren.

Ponto gebruikt voor custom integrations de OAuth 2 *client credentials*-stroom. Alle voorbeelden in de API-referentie gebruiken daarnaast een **clientcertificaat** (`--cert` en `--key`, mutual TLS). Controleer in het Ponto-dashboard of je integratie zo'n certificaat meekrijgt of nodig heeft. Werkt het zonder, laat het dan weg.

## Stap 2 — Gegevens veilig opslaan

Zet de gegevens in `.env` van je Werkbank-server, nooit in git. Werkbank gebruikt deze namen nog nergens; het is een voorstel voor je eigen code:

```dotenv
PONTO_CLIENT_ID=
PONTO_CLIENT_SECRET=
# Alleen als Ponto een clientcertificaat vereist:
PONTO_CERT_PATH=/var/www/werkbank/storage/app/ponto/certificate.pem
PONTO_KEY_PATH=/var/www/werkbank/storage/app/ponto/private_key.pem
PONTO_KEY_PASSPHRASE=
```

- Leg certificaat en sleutel buiten de webroot, leesbaar voor alleen de webserver-gebruiker.
- Zet ze **niet** in de map die `werkbank:backup` meeneemt zonder versleuteling; bewaar ze net als de `APP_KEY` in je wachtwoordkluis.
- Sandbox en live hebben **verschillende** credentials. Of je met de sandbox of met echte rekeningen werkt, bepaalt Ponto aan de hand van de credentials; de code is hetzelfde.

## Stap 3 — Toegangstoken ophalen

Elke API-aanroep vereist een toegangstoken. Een token is 30 minuten geldig; vraag gewoon een nieuwe aan als hij verlopen is.

```bash
curl -s -X POST https://api.ibanity.com/ponto-connect/oauth2/token \
  --cert certificate.pem --key private_key.pem \
  -u "$PONTO_CLIENT_ID:$PONTO_CLIENT_SECRET" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Accept: application/vnd.api+json" \
  -d grant_type=client_credentials
```

Het antwoord bevat `access_token` en `expires_in` (in seconden). Gebruik het token als `Authorization: Bearer <token>`.

In Laravel kan dit met de HTTP-client, bijvoorbeeld:

```php
$http = Http::withOptions(array_filter([
    'cert' => env('PONTO_CERT_PATH') ? [env('PONTO_CERT_PATH'), env('PONTO_KEY_PASSPHRASE')] : null,
    'ssl_key' => env('PONTO_KEY_PATH'),
]));

$token = $http->asForm()->withBasicAuth(config('services.ponto.id'), config('services.ponto.secret'))
    ->post('https://api.ibanity.com/ponto-connect/oauth2/token', ['grant_type' => 'client_credentials'])
    ->throw()->json('access_token');
```

## Stap 4 — Rekeningen en transacties ophalen

| Wat | Aanroep |
|---|---|
| Rekeningen (IBAN, saldo, vervaldatum toestemming) | `GET /ponto-connect/accounts` |
| Transacties van een rekening | `GET /ponto-connect/accounts/{accountId}/transactions` |
| Nog niet verwerkte transacties (alleen ter informatie) | `GET /ponto-connect/accounts/{accountId}/pending-transactions` |

Alle lijsten zijn gepagineerd met cursors: `page[limit]` (maximaal 100), en de link `links.next` voor de volgende pagina.

Bruikbare velden van een transactie:

| Veld | Gebruik in Werkbank |
|---|---|
| `id` | Uniek kenmerk: bewaar het om dubbel boeken te voorkomen |
| `amount` | Positief = ontvangst, negatief = afschrijving |
| `valueDate`, `executionDate` | Betaaldatum |
| `remittanceInformation` | Omschrijving, vaak met factuurnummer |
| `endToEndId` | Kenmerk dat de betaler meegaf; bij betalingen uit het Werkbank-betaalbestand `WB-<boeknummer>` |
| `counterpartName`, `counterpartReference` | Naam en IBAN van de tegenpartij |

### Synchroniseren: laat Ponto het werk doen

Ponto haalt de gegevens zelf **vier keer per dag** bij de bank op. Een API-aanroep geeft altijd de stand van de laatste geslaagde synchronisatie terug; het tijdstip staat in `meta.synchronizedAt`.

Een extra synchronisatie (`POST /ponto-connect/synchronizations`) mag volgens de voorwaarden van Ponto **alleen als de gebruiker zelf aanwezig is**, met zijn echte IP-adres in `customerIpAddress`. Een nachtelijke taak mag dus wel gegevens **lezen**, maar geen synchronisatie starten. Wil je een knop "nu bijwerken", koppel die dan aan een gebruiker die op dat moment is ingelogd. Ponto weigert een nieuwe synchronisatie kort na de vorige: de documentatie noemt een minimum van 5 tot 30 minuten.

## Stap 5 — Transacties aan facturen koppelen

Voorstel voor een eigen tabel, zodat elke banktransactie één keer wordt verwerkt en terug te vinden is:

```php
Schema::create('bank_transactions', function (Blueprint $table) {
    $table->id();
    $table->string('ponto_id')->unique();           // transaction.id uit Ponto
    $table->string('account_iban', 40);
    $table->date('date');
    $table->decimal('amount', 14, 2);
    $table->string('counterpart_name')->nullable();
    $table->string('counterpart_iban', 40)->nullable();
    $table->text('remittance')->nullable();
    $table->string('end_to_end_id', 35)->nullable();
    $table->string('status', 20)->default('nieuw');  // nieuw | gekoppeld | genegeerd
    $table->nullableMorphs('matched');               // payment of purchase_payment
    $table->timestamps();
});
```

Koppelregels, van zeker naar minder zeker:

1. **Afschrijving met `endToEndId` = `WB-<boeknummer>`:** deze betaling komt uit het betaalbestand van Werkbank (zie `App\Support\Sepa`). Koppel hem aan de inkoopfactuur met dat boeknummer, als het bedrag gelijk is aan het openstaande bedrag.
2. **Ontvangst met een factuurnummer in `remittanceInformation`:** zoek een definitieve verkoopfactuur met dat nummer. Koppel alleen als het bedrag gelijk is aan het openstaande bedrag, of als je deelbetalingen bewust toestaat.
3. **Ontvangst zonder herkenbaar nummer:** zoek een openstaande factuur met precies hetzelfde bedrag bij een relatie met dezelfde IBAN (`companies.iban`). Is er precies één kandidaat, stel die dan **voor**, maar boek niet automatisch.
4. **Al het andere** (bankkosten, salarissen, belasting) laat je staan of markeer je als "genegeerd".

Registreer een gekoppelde betaling op dezelfde manier als het scherm dat doet: met de code uit `InvoiceController::storePayment` of `PurchaseInvoiceController::storePayment`, dus een betaling aanmaken, `refreshPaid()` aanroepen en het geheel in een transactie. Vul `notes` met iets als `Ponto <transactie-id>` en schrijf een regel in het logboek (`Audit::log`).

Uitgangspunten:

- Boek automatisch alleen bij een **eenduidige** match; laat de rest door een mens bevestigen op een overzichtsscherm.
- Bewaar het Ponto-kenmerk (`ponto_id`) **uniek**, zodat een tweede run niets dubbel boekt.
- Een terugboeking (storno) is een aparte transactie met een tegengesteld bedrag; koppel die als negatieve betaling.
- Zet het ophalen in de scheduler, bijvoorbeeld elk uur tijdens kantooruren: `Schedule::command('ponto:ophalen')->hourly()->weekdays()->between('7:00', '19:00');`.

## Stap 6 (optioneel) — Inkoopfacturen betalen via Ponto

In plaats van het pain.001-bestand bij de bank te uploaden, kun je dezelfde betalingen als **bulkbetaling** bij Ponto klaarzetten:

```
POST /ponto-connect/accounts/{accountId}/bulk-payments
```

```json
{
  "data": {
    "type": "bulkPayment",
    "attributes": {
      "reference": "Werkbank WB20261026120000ABCD",
      "requestedExecutionDate": "2026-10-28",
      "batchBookingPreferred": true,
      "payments": [
        {
          "currency": "EUR",
          "amount": 121.00,
          "creditorName": "Leverancier B.V.",
          "creditorAccountReference": "NL20INGB0001234567",
          "creditorAccountReferenceType": "IBAN",
          "remittanceInformation": "Factuur INK-1",
          "remittanceInformationType": "unstructured",
          "endToEndId": "WB-1201"
        }
      ]
    }
  }
}
```

- Gebruik dezelfde selectie en controles als het scherm **Betaalbestand** (`Sepa::problem()`): goedgekeurd, niet on hold, iets openstaand, geldige IBAN. Hergebruik ook `endToEndId` = `WB-<boeknummer>`, zodat stap 5 de afschrijving later vanzelf herkent.
- Ponto voert niets uit voordat iemand de betaling **ondertekent** bij de bank. Zonder `redirectUri` gebeurt dat in het Ponto-dashboard. Met een `redirectUri` stuur je de gebruiker naar de link die je terugkrijgt, en na ondertekening komt hij terug in Werkbank.
- Volg de status met `GET /ponto-connect/accounts/{accountId}/bulk-payments/{id}`. `unsigned` betekent dat de betaling nog moet worden ondertekend, `rejected` dat de bank haar heeft geweigerd.
- Live ondertekenen kan pas nadat Isabel je organisatie heeft geverifieerd (wettelijke vertegenwoordigers en uiteindelijk belanghebbenden).
- In de API-referentie moeten betaalverzoeken van live-applicaties bovendien **ondertekend** worden (HTTP-headers `Signature` en `Digest`). Controleer of dat ook voor custom integrations geldt.
- Maximaal 1000 betalingen per bulkbetaling, alleen in euro's en naar SEPA-IBAN's.

## Stap 7 — Live gaan

1. Test alles in de sandbox. Je kunt daar zelf testtransacties aanmaken via de sandbox-API, of de willekeurige transacties gebruiken.
2. Zet het Ponto-dashboard op **live**, voeg je echte rekeningen toe en maak een nieuwe custom integration.
3. Vervang in `.env` de sandbox-credentials door de live-credentials en voer `php artisan config:cache` uit. Andere wijzigingen zijn niet nodig.
4. Laat de koppeling de eerste weken alleen **voorstellen** doen, en zet automatisch boeken pas aan als de voorstellen kloppen.

## Onderhoud

- **Toestemming verloopt:** de bank geeft toestemming voor een beperkte periode. Het veld `authorizationExpirationExpectedAt` per rekening vertelt wanneer. Toon een waarschuwing in Werkbank als die datum binnen twee weken valt. Vernieuwen doet de gebruiker in het Ponto-dashboard; synchronisaties geven anders de fout `authorizationExpired`.
- **Storingen bij de bank:** let op `meta.availability` = `readonly`. De bestaande gegevens zijn dan nog leesbaar, maar er komt niets nieuws binnen.
- **Intrekken:** verwijder de integratie in het Ponto-dashboard. Werkbank kan daarna niets meer ophalen.

## Beveiliging en privacy

- Banktransacties bevatten persoonsgegevens: namen, IBAN's en omschrijvingen van tegenpartijen. Toon ze alleen aan rollen met financiële rechten (in Werkbank `facturen.bewerken` of `inkoop.goedkeuren`) en neem de tabel op in je privacybeleid en bewaartermijnen.
- Geef de integratie alleen toegang tot de rekeningen die Werkbank nodig heeft, en alleen **AIS** zolang je niet via Ponto betaalt.
- Log geen tokens, secrets of volledige API-antwoorden.
- Een gestolen client secret geeft toegang tot je rekeninginformatie. Genereer het secret direct opnieuw in Ponto als je twijfelt.

## Checklist

- [ ] Ponto-account en sandbox-integratie (AIS) aangemaakt
- [ ] Credentials in `.env`; certificaat en sleutel (indien nodig) veilig opgeslagen
- [ ] Token ophalen en rekeningen lezen werkt in de sandbox
- [ ] Tabel `bank_transactions` met unieke `ponto_id`
- [ ] Koppelregels getest met sandbox-transacties, inclusief deelbetaling en storno
- [ ] Overzichtsscherm voor voorstellen en niet-gekoppelde transacties
- [ ] Geplande taak die alleen leest (geen synchronisaties zonder gebruiker)
- [ ] Waarschuwing voor verlopende toestemming
- [ ] Live-integratie, eerst alleen voorstellen
- [ ] (Optioneel) PIS: bulkbetalingen, ondertekenen, statussen volgen

## Bronnen

- [Ponto: Integrations in a nutshell](https://documentation.myponto.com/developers/integrations-in-a-nutshell)
- [Ponto: Custom integrations](https://documentation.myponto.com/developers/custom-integrations) (opzet, synchronisatie, betalingen, sandbox, live gaan)
- [Ponto Connect API-referentie](https://documentation.isabel.eu/ponto-connect/api) (token, rekeningen, transacties, synchronisaties, bulkbetalingen)
- [Ponto: prijzen](https://myponto.com/en/pricing/)
