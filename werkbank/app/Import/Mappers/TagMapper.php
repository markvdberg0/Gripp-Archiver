<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\Gripp;
use App\Import\ImportSkip;
use App\Import\Mapper;
use App\Models\Tag;

class TagMapper extends Mapper
{
    public function entity(): string
    {
        return 'tag';
    }

    public function model(): string
    {
        return Tag::class;
    }

    public function map(array $row, Context $ctx): ?array
    {
        $name = Gripp::str($row['name'] ?? $row['searchname'] ?? null, 100) ?? throw new ImportSkip('Label zonder naam.');
        // Labels zijn uniek op naam: bestaat er al een lokaal label met die naam, dan krijgt deze een achtervoegsel.
        if (Tag::where('name', $name)->where(fn ($q) => $q->whereNull('gripp_id')->orWhere('gripp_id', '!=', $row['id']))->exists()) {
            $name = mb_substr($name, 0, 90).' ('.$row['id'].')';
        }

        return ['name' => $name];
    }
}
