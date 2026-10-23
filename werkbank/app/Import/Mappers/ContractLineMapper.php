<?php

namespace App\Import\Mappers;

use App\Import\Context;
use App\Import\LineMapper;
use App\Models\Contract;
use App\Models\ContractLine;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class ContractLineMapper extends LineMapper
{
    public function entity(): string
    {
        return 'contractline';
    }

    public function model(): string
    {
        return ContractLine::class;
    }

    protected function parentColumn(): string
    {
        return 'contract_id';
    }

    protected function parentId(array $row, Context $ctx): ?int
    {
        return $ctx->id(Contract::class, $row['contract'] ?? null);
    }

    public function map(array $row, Context $ctx): ?array
    {
        return parent::map($row, $ctx) + ['ledger_id' => $ctx->id(Ledger::class, $row['ledger'] ?? null)];
    }

    /** Contracttotalen per periode uit de regels (Gripp levert ze niet). */
    public function checks(Context $ctx): array
    {
        if (! $ctx->dryRun) {
            foreach (Contract::whereNotNull('gripp_id')->with('lines')->get() as $contract) {
                $t = $contract->computeTotals();
                DB::table('contracts')->where('id', $contract->id)->update(['total_excl' => $t['excl'], 'total_vat' => $t['vat'], 'total_incl' => $t['incl']]);
            }
        }

        return parent::checks($ctx);
    }
}
