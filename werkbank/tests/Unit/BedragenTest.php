<?php

namespace Tests\Unit;

use App\Support\Bedragen;
use PHPUnit\Framework\TestCase;

class BedragenTest extends TestCase
{
    public function test_regeltotaal(): void
    {
        $this->assertSame('2469.00', Bedragen::lineTotal('normaal', 2, 1234.5, 0));
        $this->assertSame('270.00', Bedragen::lineTotal('normaal', 3, 100, 10));
        $this->assertSame('0.33', Bedragen::lineTotal('normaal', 1, 0.333, 0));
        $this->assertSame('-50.00', Bedragen::lineTotal('normaal', -1, 50, 0));
        $this->assertSame('0.00', Bedragen::lineTotal('groep', 4, 100, 0));
        $this->assertSame('0.00', Bedragen::lineTotal('tekst', 1, 100, 0));
    }

    public function test_totalen_per_btw_tarief(): void
    {
        $lines = [
            (object) ['row_type' => 'normaal', 'total_excl' => '100.00', 'vat_rate_id' => 1],
            (object) ['row_type' => 'normaal', 'total_excl' => '33.33', 'vat_rate_id' => 1],
            (object) ['row_type' => 'normaal', 'total_excl' => '50.00', 'vat_rate_id' => 2],
            (object) ['row_type' => 'groep', 'total_excl' => '999.00', 'vat_rate_id' => 1],
            (object) ['row_type' => 'normaal', 'total_excl' => '10.00', 'vat_rate_id' => null],
        ];
        $t = Bedragen::totals($lines, [1 => 21.0, 2 => 9.0]);
        $this->assertSame('193.33', $t['excl']);
        $this->assertSame('32.50', $t['vat']); // 28,00 (21% van 133,33) + 4,50
        $this->assertSame('225.83', $t['incl']);
        $this->assertSame('28.00', $t['per_rate'][1]['vat']);
    }

    public function test_invoer_parsen_en_tonen(): void
    {
        $this->assertSame('1234.56', Bedragen::parse('1.234,56'));
        $this->assertSame('1234.56', Bedragen::parse('1234,56'));
        $this->assertSame('1234.56', Bedragen::parse('1234.56'));
        $this->assertSame('-12', Bedragen::parse('-12'));
        $this->assertSame('1250', Bedragen::parse('€ 1250'));
        $this->assertNull(Bedragen::parse(''));
        $this->assertSame('abc', Bedragen::parse('abc'));
        $this->assertSame('1234,50', Bedragen::input('1234.5'));
        $this->assertSame('2', Bedragen::input('2.0000', 0));
        $this->assertSame('0,125', Bedragen::input('0.125'));
    }
}
