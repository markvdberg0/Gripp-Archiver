<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\Mapper;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Model;

class ContactMapper extends Mapper
{
    public function entity(): string
    {
        return 'contact';
    }

    public function model(): string
    {
        return Contact::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $companyId = $ctx->id(Company::class, $row['company'] ?? null);
        if (Gripp::id($row['company'] ?? null) && ! $companyId) {
            $ctx->issue('contact', $row['id'], 'verwijzing', 'Relatie '.Gripp::id($row['company']).' bestaat niet; contactpersoon zonder relatie geïmporteerd.');
        }
        $s = fn ($k, $max = 255) => Gripp::str($row[$k] ?? null, $max);

        return [
            'company_id' => $companyId,
            'salutation' => match (true) {
                Gripp::is($row['salutation'] ?? null, 'de heer of mevrouw') => 'dhr_mevr',
                Gripp::is($row['salutation'] ?? null, 'mevrouw', 'mevr.', 'mrs') => 'mevr',
                Gripp::is($row['salutation'] ?? null, 'de heer', 'dhr.', 'mr') => 'dhr',
                default => null,
            },
            'initials' => $s('initials', 20),
            'first_name' => $s('firstname', 100),
            'infix' => $s('infix', 30),
            'last_name' => $s('lastname', 100) ?? $s('searchname', 100) ?? 'Onbekend',
            'function' => $s('function', 100),
            'department' => $s('department', 100),
            'email' => $s('email'),
            'phone' => $s('phone', 40),
            'mobile' => $s('mobile', 40),
            'show_on_company' => Gripp::bool($row['showoncompanycard'] ?? true),
            'active' => Gripp::bool($row['active'] ?? true),
            'notes' => Gripp::text($row['notes'] ?? null),
        ];
    }

    public function after(Model $model, array $row, Context $ctx): void
    {
        $this->syncTags($model, $row, $ctx);
    }
}
