<?php

namespace Tests\Support;

use Illuminate\Support\Facades\File;

/**
 * Bouwt een nep-back-upmap in hetzelfde formaat als de Gripp Back-up (data/current/raw, state.json, archive/).
 * De records hebben de vorm van de Gripp API: verwijzingen als { id, searchname }, datums als { date, timezone }.
 */
class FakeBackup
{
    public string $dir;

    public array $data;

    /** @var array<int, array{name: string, content: string}> */
    public array $files = [];

    public array $invoicePdfs = [];

    public array $documents = [];

    public static function make(): self
    {
        $b = new self;
        $b->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'werkbank-backup-'.bin2hex(random_bytes(5));
        $b->data = self::defaultData();
        $b->files = [
            9001 => ['name' => 'kvk-uittreksel.pdf', 'content' => "%PDF-1.4\nkvk\n%%EOF"],
            9002 => ['name' => 'mail.pdf', 'content' => "%PDF-1.4\nmail\n%%EOF"],
            9004 => ['name' => 'kopie.pdf', 'content' => "%PDF-1.4\nkvk\n%%EOF"], // zelfde inhoud als 9001
        ];

        return $b;
    }

    public static function ref(int $id, string $name = '', ?string $discr = null): array
    {
        return array_filter(['id' => $id, 'searchname' => $name, 'discr' => $discr], fn ($v) => $v !== null);
    }

    public static function date(string $ymd): array
    {
        return ['date' => (strlen($ymd) === 10 ? $ymd.' 00:00:00' : $ymd).'.000000', 'timezone_type' => 3, 'timezone' => 'Europe/Amsterdam'];
    }

    public static function defaultData(): array
    {
        $r = [self::class, 'ref'];
        $d = [self::class, 'date'];

        return [
            'tag' => [['id' => 1, 'name' => 'Zwolle', 'searchname' => 'Zwolle'], ['id' => 2, 'name' => 'Groot', 'searchname' => 'Groot']],
            'employee' => [
                ['id' => 100, 'firstname' => 'Jan', 'lastname' => 'Jansen', 'searchname' => 'Jan Jansen', 'email' => 'jan@example.test', 'active' => true, 'number' => 1,
                    'username' => 'jan', 'password' => '$2y$10$geheimehash', 'socialsecuritynumber' => '123456782', 'bankaccount' => 'NL00BANK0123456789', 'createdon' => $d('2024-01-02')],
                ['id' => 103, 'firstname' => 'Anna', 'infix' => 'de', 'lastname' => 'Vries', 'searchname' => 'Anna de Vries', 'active' => true],
            ],
            'file' => [
                ['id' => 9001, 'title' => 'kvk-uittreksel.pdf', 'searchname' => 'kvk-uittreksel.pdf', 'createdon' => $d('2025-03-01')],
                ['id' => 9002, 'title' => 'mail.pdf', 'searchname' => 'mail.pdf'],
                ['id' => 9003, 'title' => 'ontbreekt.pdf', 'searchname' => 'ontbreekt.pdf'],
                ['id' => 9004, 'title' => 'kopie.pdf', 'searchname' => 'kopie.pdf'],
            ],
            'company' => [
                ['id' => 1, 'companyname' => 'Bakker Installatie B.V.', 'searchname' => 'Bakker Installatie B.V.', 'customernumber' => 1000001, 'active' => true,
                    'relationtype' => $r(1, 'Bedrijf'), 'companyroles' => [$r(2, 'Klant')], 'email' => 'info@bakker.test', 'phone' => '038-1234567',
                    'visitingaddress_street' => 'Dorpsstraat', 'visitingaddress_streetnumber' => '1', 'visitingaddress_zipcode' => '8011 AA', 'visitingaddress_city' => 'Zwolle',
                    'cocnumber' => '12345678', 'vatnumber' => 'NL001234567B01', 'bankaccount' => 'NL91ABNA0417164300', 'paymentmethod' => $r(1, 'Overboeking'),
                    'invoicesendby' => $r(2, 'E-mail'), 'accountmanager' => $r(100, 'Jan Jansen', 'medewerker'), 'tags' => [$r(1, 'Zwolle')], 'files' => [$r(9001, 'kvk-uittreksel.pdf')],
                    'createdon' => $d('2025-01-10 09:30:00')],
                ['id' => 2, 'companyname' => 'Staal & Co', 'searchname' => 'Staal & Co', 'customernumber' => 1000002, 'active' => true, 'companyroles' => [$r(3, 'Leverancier')],
                    'paymentmethod' => $r(2, 'Automatische incasso'), 'invoicesendby' => $r(1, 'Post'), 'termofpayment_purchaseinvoice' => 14],
                ['id' => 3, 'companyname' => 'Oud Klant B.V.', 'searchname' => 'Oud Klant B.V.', 'customernumber' => 1000003, 'active' => false, 'companyroles' => [$r(4, 'Prospect'), $r(2, 'Klant')]],
            ],
            'contact' => [
                ['id' => 11, 'firstname' => 'Piet', 'lastname' => 'Bakker', 'company' => $r(1, 'Bakker Installatie B.V.', 'bedrijf'), 'email' => 'piet@bakker.test', 'salutation' => $r(1, 'De heer'), 'active' => true],
                ['id' => 12, 'firstname' => 'Sara', 'lastname' => 'Staal', 'company' => $r(2, 'Staal & Co', 'bedrijf'), 'salutation' => $r(2, 'Mevrouw'), 'active' => true, 'tags' => [$r(2, 'Groot')]],
                ['id' => 13, 'firstname' => 'Wees', 'lastname' => 'Contact', 'company' => $r(99999, 'bestaat niet', 'bedrijf')],
            ],
            // Fase 2
            'unit' => [['id' => 1, 'name' => 'uur', 'unittype' => $r(1, 'Uren')], ['id' => 3, 'name' => 'stuks', 'unittype' => $r(2, 'Aantallen')]],
            'ledger' => [
                ['id' => 8000, 'number' => '8000', 'name' => 'Omzet', 'type' => $r(1, 'Resultatenrekening'), 'debitcredit' => $r(2, 'Credit / Verkoop'), 'active' => true],
                ['id' => 7000, 'number' => '7000', 'name' => 'Inkoopwaarde', 'type' => $r(1, 'Resultatenrekening'), 'debitcredit' => $r(1, 'Debet / Inkoop'), 'active' => true],
            ],
            'costheading' => [['id' => 1, 'number' => 'KP-MON', 'name' => 'Montage']],
            'product' => [
                ['id' => 66, 'number' => 37002, 'name' => 'Elektrische inrichting', 'sellingprice' => '5500.00', 'buyingprice' => '3000.00', 'unit' => $r(1, 'uur'),
                    'vat' => $r(27, 'BTW 21%'), 'buyingvat' => $r(127, 'BTW 21%'), 'ledger' => $r(8000), 'purchaseledger' => $r(7000), 'invoicebasis' => $r(1, 'Fixed'),
                    'supplier' => $r(2, 'Staal & Co'), 'supplierordercode' => 'EI-1', 'customfield_voorraad' => '4', 'tags' => [$r(2, 'Groot')]],
                ['id' => 67, 'number' => 37003, 'name' => 'Transformator', 'sellingprice' => '15300.00', 'buyingprice' => '12000.00', 'unit' => $r(3, 'stuks'), 'vat' => $r(27, 'BTW 21%'), 'archived' => false],
            ],
            'packet' => [['id' => 1, 'number' => 1, 'name' => 'Standaard station', 'unit' => $r(3)]],
            'packetline' => [
                ['id' => 1, 'packet' => $r(1), 'product' => $r(67, 'Transformator (37003)'), 'amount' => 1, 'sellingprice' => '15300.00', 'discount' => 0, 'vat' => $r(27, 'BTW 21%'), 'rowtype' => $r(1, 'NORMAAL'), '_ordering' => 0],
            ],
            'offerphase' => [['id' => 1, 'name' => 'Acquisitie', '_ordering' => 0], ['id' => 5, 'name' => 'Beoordeling', '_ordering' => 4]],
            'offer' => [
                ['id' => 217, 'number' => 1002, 'name' => 'Prijsaanvraag compactstation', 'company' => $r(1, 'Bakker', 'bedrijf'), 'contact' => $r(11), 'phase' => $r(1),
                    'status' => $r(9, 'Verzonden'), 'acceptancestatus' => $r(1, 'Geaccepteerd'), 'acceptedon' => $d('2025-05-20'), 'date' => $d('2025-05-15'),
                    'validity' => $r(1, 'Standaard (offerte 30 dagen geldig)'), 'chance' => 50, 'accountmanager' => $r(100), 'totalexclvat' => '26750.00', 'totalinclvat' => '32367.50',
                    'customfield_levertijd' => '12 weken', 'template' => $r(42, 'Offerte Project'), 'description' => '<p>Inleiding</p>'],
                ['id' => 218, 'number' => 1003, 'name' => 'Concept station', 'company' => $r(2), 'status' => $r(4, 'Concept'), 'date' => $d('2026-09-01'), 'totalexclvat' => '0.00', 'totalinclvat' => '0.00'],
                ['id' => 219, 'name' => 'Basis compactstation', 'isbasis' => true, 'status' => $r(4, 'Concept'), 'date' => $d('2025-01-01'), 'totalexclvat' => '0.00', 'totalinclvat' => '0.00'],
            ],
            'offerprojectline' => [
                ['id' => 107, 'offerprojectbase' => $r(217, '', 'offerte'), 'rowtype' => $r(2, 'GROEPLABEL'), 'additionalsubject' => 'Compactstation', 'amount' => 4, 'sellingprice' => '0.00', 'discount' => 0, 'product' => $r(2, 'Groep', 'dummyproduct'), 'vat' => $r(27, 'BTW 21%'), '_ordering' => 0],
                ['id' => 111, 'offerprojectbase' => $r(217, '', 'offerte'), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => '', 'product' => $r(66, 'Elektrische inrichting (37002)'), 'amount' => 2, 'sellingprice' => '5500.00', 'buyingprice' => '3000.00', 'discount' => 0, 'unit' => $r(1, 'uur'), 'vat' => $r(27, 'BTW 21%'), '_ordering' => 1, 'description' => 'Inclusief aansluiten'],
                ['id' => 112, 'offerprojectbase' => $r(217, '', 'offerte'), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Transformator 630 kVA', 'product' => $r(67), 'amount' => 1, 'sellingprice' => '17500.00', 'discount' => 10, 'unit' => $r(3), 'vat' => $r(27, 'BTW 21%'), '_ordering' => 2],
                // hoort bij een opdracht, niet bij een offerte
                ['id' => 900, 'offerprojectbase' => $r(901, '', 'opdracht'), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Uitvoering', 'amount' => 10, 'sellingprice' => '100.00', 'discount' => 0, 'vat' => $r(27, 'BTW 21%'), '_ordering' => 0],
            ],
            // Fase 3
            'projectphase' => [['id' => 1, 'name' => 'Werkvoorbereiding', '_ordering' => 1], ['id' => 2, 'name' => 'Uitvoering', '_ordering' => 2]],
            'project' => [
                ['id' => 901, 'number' => 890140, 'name' => 'Compactstation Zwolle', 'company' => $r(1, 'Bakker', 'bedrijf'), 'phase' => $r(2), 'accountmanager' => $r(100),
                    'startdate' => $d('2025-06-01'), 'deadline' => $d('2025-09-01'), 'totalexclvat' => '1000.00', 'totalinclvat' => '1210.00', 'employees' => [$r(100), $r(103)],
                    'customfield_materiaalplanning' => 'week 30', 'workdeliveraddress' => 'Industrieweg 5, Zwolle'],
            ],
            'tasktype' => [['id' => 1, 'name' => 'Montage', '_ordering' => 0]],
            'taskphase' => [['id' => 1, 'name' => 'Open', '_ordering' => 0]],
            'task' => [
                ['id' => 31, 'number' => 1001, 'content' => 'Kabel afroepen', 'type' => $r(1), 'phase' => $r(1), 'company' => $r(1), 'offerprojectbase' => $r(901, '', 'opdracht'),
                    'preferredemployee' => $r(103), 'deadlinedate' => $d('2025-08-01'), 'deadlinetime' => $d('1970-01-01 14:30:00'), 'estimatedhours' => 2, 'isafgerond' => false],
            ],
            'calendaritem' => [
                ['id' => 41, 'calendaritememployee' => $r(103), 'task' => $r(31), 'date' => $d('2025-07-30'), 'time' => $d('1970-01-01 09:00:00'), 'hours' => 1.5, 'subject' => 'Kabel afroepen'],
            ],
            'hour' => [
                ['id' => 51, 'date' => $d('2025-07-01'), 'employee' => $r(100), 'offerprojectbase' => $r(901, '', 'opdracht'), 'offerprojectline' => $r(900), 'amount' => 8, 'status' => $r(3, 'Gefiatteerd'), 'authorizedby' => $r(103), 'authorizedon' => $d('2025-07-05'), 'description' => 'Montage'],
                ['id' => 52, 'date' => $d('2025-07-02'), 'employee' => $r(100), 'offerprojectbase' => $r(901, '', 'opdracht'), 'amount' => 2, 'status' => $r(1, 'Concept')],
            ],
            // Fase 4
            'contract' => [
                ['id' => 61, 'number' => 50002, 'name' => 'Huur containerstation', 'company' => $r(1), 'status' => $r(1, 'Actief'), 'frequency' => $r(1, 'Iedere maand'), 'date' => $d('2026-07-14'), 'subject_invoice' => 'Huur station'],
            ],
            'contractline' => [
                ['id' => 62, 'contract' => $r(61), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Huur', 'amount' => 1, 'sellingprice' => '850.00', 'discount' => 0, 'vat' => $r(27, 'BTW 21%'), 'ledger' => $r(8000), '_ordering' => 0],
            ],
            'invoice' => [
                ['id' => 2001, 'number' => 200441, 'company' => $r(1), 'contact' => $r(11), 'status' => $r(3, 'Verzonden'), 'fase' => 'Herinnering', 'subject' => 'Aanbetaling opdracht 890140',
                    'date' => $d('2025-07-01'), 'expirydate' => $d('2025-07-31'), 'totalincldiscountexclvat' => '1000.00', 'totalinclvat' => '1210.00', 'totalpayed' => '500.00', 'totalopeninclvat' => '710.00', 'paymentmethod' => $r(1, 'Overboeking')],
                ['id' => 2002, 'number' => 200442, 'company' => $r(1), 'status' => $r(3, 'Verzonden'), 'fase' => 'Factuur', 'subject' => 'Creditnota', 'date' => $d('2025-08-01'), 'expirydate' => $d('2025-08-31'),
                    'totalincldiscountexclvat' => '-100.00', 'totalinclvat' => '-121.00', 'totalpayed' => '-121.00', 'totalopeninclvat' => '0.00'],
                ['id' => 2003, 'company' => $r(2), 'status' => $r(1, 'Concept'), 'subject' => 'Nog te versturen', 'totalincldiscountexclvat' => '50.00', 'totalinclvat' => '60.50', 'totalpayed' => '0.00', 'totalopeninclvat' => '60.50'],
            ],
            'invoiceline' => [
                ['id' => 3001, 'invoice' => $r(2001), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Aanbetaling', 'amount' => 1, 'sellingprice' => '1000.00', 'discount' => 0, 'vat' => $r(27, 'BTW 21%'),
                    'ledger' => $r(8000), 'project' => $r(901), 'part' => $r(900), '_ordering' => 0],
                ['id' => 3002, 'invoice' => $r(2002), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Correctie', 'amount' => -1, 'sellingprice' => '100.00', 'discount' => 0, 'vat' => $r(27, 'BTW 21%'), '_ordering' => 0],
                ['id' => 3003, 'invoice' => $r(2003), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Advies', 'amount' => 1, 'sellingprice' => '50.00', 'discount' => 0, 'vat' => $r(27, 'BTW 21%'), '_ordering' => 0],
            ],
            'payment' => [
                ['id' => 4001, 'invoice' => $r(2001), 'date' => $d('2025-07-10'), 'amount' => '500.00', 'notes' => 'Deelbetaling'],
                ['id' => 4002, 'invoice' => $r(2002), 'date' => $d('2025-08-02'), 'amount' => '-121.00'],
            ],
            // Fase 5
            'purchaseorder' => [
                ['id' => 8001, 'number' => 202505171, 'name' => 'Transformator', 'company' => $r(2, 'Staal & Co'), 'supplier' => $r(77777, 'geen relatie'), 'purchaseorderstatus' => $r(2, 'Verzonden'), 'phase' => $r(2, 'Bevestigd'),
                    'date' => $d('2025-06-10'), 'deadline' => $d('2025-07-01'), 'totalincldiscountexclvat' => '12000.00', 'accountmanager' => $r(100)],
            ],
            'purchaseorderline' => [
                ['id' => 8501, 'purchaseorder' => $r(8001), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Transformator 630 kVA', 'amount' => 1, 'unit' => 'stuks', 'sellingprice' => '12000.00', 'discount' => 0,
                    'vat' => $r(127, 'BTW 21%'), 'ledger' => $r(7000), 'project' => $r(901, '', 'opdracht'), 'projectline' => $r(900), 'ordercodesupplier' => 'TR-630', '_ordering' => 0],
            ],
            'purchaseinvoice' => [
                ['id' => 6001, 'number' => 'INK-1', 'bookingnumber' => 1201, 'company' => $r(2), 'phase' => $r(4, 'Verwerkt'), 'date' => $d('2025-07-05'), 'expirydate' => $d('2025-08-04'),
                    'totalinclvat' => '14520.00', 'totalpayed' => '10000.00', 'totalopeninclvat' => '4520.00', 'files' => [$r(9001, 'kvk-uittreksel.pdf')]],
                ['id' => 6002, 'number' => 'DE-77', 'bookingnumber' => 1202, 'company' => $r(2), 'phase' => $r(5, 'On-hold'), 'unprocessed' => true, 'date' => $d('2025-07-06'),
                    'totalinclvat' => '1000.00', 'totalpayed' => '0.00', 'totalopeninclvat' => '1000.00'],
                ['id' => 6003, 'number' => 'INK-1', 'bookingnumber' => 1201, 'company' => $r(1), 'phase' => $r(1, 'Concept'), 'date' => $d('2025-07-07'),
                    'totalinclvat' => '0.00', 'totalpayed' => '0.00', 'totalopeninclvat' => '0.00'], // dubbel boeknummer
            ],
            'purchaseinvoiceline' => [
                ['id' => 7001, 'purchaseinvoice' => $r(6001), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Transformator', 'amount' => 1, 'sellingprice' => '12000.00', 'discount' => 0,
                    'vat' => $r(127, 'BTW 21%'), 'ledger' => $r(7000), 'purchaseorderline' => $r(8501), 'project' => $r(901, '', 'opdracht'), '_ordering' => 0],
                ['id' => 7002, 'purchaseinvoice' => $r(6002), 'rowtype' => $r(1, 'NORMAAL'), 'additionalsubject' => 'Onderdelen uit Duitsland', 'amount' => 1, 'sellingprice' => '1000.00', 'discount' => 0,
                    'vat' => $r(137, 'BTW binnen EU 21%'), 'costheading' => $r(1), '_ordering' => 0],
            ],
            'purchasepayment' => [['id' => 7501, 'purchaseinvoice' => $r(6001), 'date' => $d('2025-07-20'), 'amount' => '10000.00', 'notes' => 'Deel']],
            'timelineentry' => [
                ['id' => 501, 'company' => $r(1), 'contact' => $r(11), 'employee' => $r(103, 'Anna de Vries'), 'timelinetype' => $r(4, 'RECEIVEDEMAIL'), 'date' => $d('2026-09-22 10:15:00'),
                    'subject' => 'Aanvraag', 'message' => '<p>Graag een <b>offerte</b><script>alert(1)</script><img src="https://evil.test/x.png"></p>', 'files' => [$r(9002, 'mail.pdf')]],
                ['id' => 502, 'company' => $r(1), 'offer' => $r(217), 'timelinetype' => $r(1, 'USERMESSAGE'), 'date' => $d('2026-09-23'), 'message' => 'Teruggebeld'],
                ['id' => 503, 'company' => $r(2), 'timelinetype' => $r(2, 'SYSTEMMESSAGE'), 'date' => $d('2026-09-24'), 'message' => 'Status gewijzigd', 'files' => [$r(9003, 'ontbreekt.pdf'), $r(9004, 'kopie.pdf')]],
            ],
        ];
    }

    public function set(string $entity, array $rows): self
    {
        $this->data[$entity] = $rows;

        return $this;
    }

    /** Wijzig één record (op id) in de brondata. */
    public function update(string $entity, int $id, array $changes): self
    {
        foreach ($this->data[$entity] as &$row) {
            if ($row['id'] === $id) {
                $row = array_merge($row, $changes);
            }
        }

        return $this;
    }

    public function remove(string $entity, int $id): self
    {
        $this->data[$entity] = array_values(array_filter($this->data[$entity], fn ($r) => $r['id'] !== $id));

        return $this;
    }

    public function write(): self
    {
        File::deleteDirectory($this->dir.'/data');
        $raw = $this->dir.'/data/current/raw';
        File::ensureDirectoryExists($raw);
        foreach ($this->data as $entity => $rows) {
            file_put_contents("$raw/$entity.json", json_encode(array_values($rows)));
        }
        $index = ['files' => [], 'invoicePdfs' => []];
        foreach ($this->files as $id => $f) {
            $rel = "files/$id/{$f['name']}";
            File::ensureDirectoryExists($this->dir."/archive/files/$id");
            file_put_contents($this->dir.'/archive/'.$rel, $f['content']);
            $index['files'][$id] = ['ok' => true, 'name' => $f['name'], 'path' => $rel, 'size' => strlen($f['content']), 'sha256' => hash('sha256', $f['content'])];
        }
        foreach ($this->invoicePdfs as $invoiceId => $content) {
            $rel = "invoices/factuur_$invoiceId.pdf";
            File::ensureDirectoryExists($this->dir.'/archive/invoices');
            file_put_contents($this->dir.'/archive/'.$rel, $content);
            $index['invoicePdfs'][$invoiceId] = ['ok' => true, 'path' => $rel, 'size' => strlen($content), 'sha256' => hash('sha256', $content)];
        }
        $docs = [];
        foreach ($this->documents as $entity => $items) {
            foreach ($items as $id => $content) {
                $rel = "documenten/{$entity}_$id.pdf";
                File::ensureDirectoryExists($this->dir.'/archive/documenten');
                file_put_contents($this->dir.'/archive/'.$rel, $content);
                $docs[$entity][$id] = ['ok' => true, 'path' => $rel, 'size' => strlen($content), 'sha256' => hash('sha256', $content)];
            }
        }
        file_put_contents($this->dir.'/archive/index.json', json_encode($index));
        file_put_contents($this->dir.'/data/current/documents.json', json_encode($docs));
        file_put_contents($this->dir.'/data/current/state.json', json_encode(['batch' => 'staging-test', 'status' => 'complete', 'startedAt' => now()->toIso8601String()]));

        return $this;
    }

    public function cleanup(): void
    {
        File::deleteDirectory($this->dir);
    }
}
