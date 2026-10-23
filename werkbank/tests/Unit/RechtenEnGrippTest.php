<?php

namespace Tests\Unit;

use App\Import\Gripp;
use App\Support\Rechten;
use PHPUnit\Framework\TestCase;

class RechtenEnGrippTest extends TestCase
{
    public function test_rechtenmatrix(): void
    {
        $this->assertTrue(Rechten::heeft('beheerder', 'beheer.gebruikers'));
        $this->assertTrue(Rechten::heeft('administratie', 'facturen.bewerken'));
        $this->assertFalse(Rechten::heeft('administratie', 'beheer.gebruikers'));
        $this->assertTrue(Rechten::heeft('projectleider', 'offertes.bewerken'));
        $this->assertFalse(Rechten::heeft('projectleider', 'relaties.financieel'));
        $this->assertFalse(Rechten::heeft('medewerker', 'relaties.bewerken'));
        $this->assertFalse(Rechten::heeft('alleen_lezen', 'offertes.bewerken'));
        $this->assertFalse(Rechten::heeft('onbekend', 'relaties.bekijken'));
        $this->assertFalse(Rechten::heeft(null, 'relaties.bekijken'));
        // elk recht in de matrix bestaat
        foreach (array_keys(Rechten::ROLLEN) as $rol) {
            foreach (Rechten::vanRol($rol) as $r) {
                $this->assertArrayHasKey($r, Rechten::RECHTEN);
            }
        }
    }

    public function test_gripp_hulpfuncties(): void
    {
        $this->assertSame(5, Gripp::id(['id' => 5, 'searchname' => 'x']));
        $this->assertNull(Gripp::id(0));
        $this->assertNull(Gripp::id(['id' => null]));
        $this->assertSame([1, 2], Gripp::ids([['id' => 1], ['id' => 2], ['id' => 1]]));
        $this->assertSame([7], Gripp::ids(['id' => 7]));
        $this->assertSame('Klant', Gripp::label(['id' => 2, 'searchname' => 'Klant']));
        $this->assertTrue(Gripp::is(['searchname' => 'Automatische incasso'], 'automatische incasso'));
        $this->assertSame('12.50', Gripp::money('12.5'));
        $this->assertSame('0.00', Gripp::money(null));
        $this->assertNull(Gripp::str('   '));
    }
}
