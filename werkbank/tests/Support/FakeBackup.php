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
            'timelineentry' => [
                ['id' => 501, 'company' => $r(1), 'contact' => $r(11), 'employee' => $r(103, 'Anna de Vries'), 'timelinetype' => $r(4, 'RECEIVEDEMAIL'), 'date' => $d('2026-09-22 10:15:00'),
                    'subject' => 'Aanvraag', 'message' => '<p>Graag een <b>offerte</b><script>alert(1)</script><img src="https://evil.test/x.png"></p>', 'files' => [$r(9002, 'mail.pdf')]],
                ['id' => 502, 'company' => $r(1), 'timelinetype' => $r(1, 'USERMESSAGE'), 'date' => $d('2026-09-23'), 'message' => 'Teruggebeld'],
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
