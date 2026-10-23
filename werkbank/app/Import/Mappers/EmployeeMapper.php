<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Employee;

/**
 * Medewerkers. Bewust NIET overgenomen (dataminimalisatie): gebruikersnaam, wachtwoord(-hash),
 * BSN, bankgegevens, privéadres en gezinsleden. Inloggen gaat via eigen Werkbank-gebruikers.
 */
class EmployeeMapper extends Mapper
{
    private const GEVOELIG = ['username', 'password', 'socialsecuritynumber', 'bankaccount', 'bankcity', 'bankascription', 'bic', 'dateofbirth', 'street', 'streetnumber', 'zipcode', 'city'];

    public function entity(): string
    {
        return 'employee';
    }

    public function model(): string
    {
        return Employee::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        return [
            'number' => is_numeric($row['number'] ?? null) ? (int) $row['number'] : null,
            'first_name' => Gripp::str($row['firstname'] ?? null, 100),
            'infix' => Gripp::str($row['infix'] ?? null, 30),
            'last_name' => Gripp::str($row['lastname'] ?? null, 100) ?? Gripp::str($row['searchname'] ?? null, 100) ?? 'Onbekend',
            'initials' => Gripp::str($row['initials'] ?? null, 20),
            'email' => Gripp::str($row['email'] ?? null),
            'phone' => Gripp::str($row['phone'] ?? null, 40),
            'mobile' => Gripp::str($row['mobile'] ?? null, 40),
            'function' => Gripp::str($row['function'] ?? null, 100),
            'employed_since' => Gripp::day($row['employeesince'] ?? null),
            'active' => Gripp::bool($row['active'] ?? true),
        ];
    }

    /** Gevoelige velden worden niet eens gehasht. */
    public function hashRow(array $row): array
    {
        return array_diff_key($row, array_flip(self::GEVOELIG));
    }
}
