<?php

namespace App\Http\Controllers;

use App\Models\Hour;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\OfferPhase;
use App\Models\PurchaseInvoice;
use App\Support\Csv;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Overzichten. Elk overzicht kan als CSV worden gedownload (?export=csv). */
class ReportController extends Controller
{
    private const BUCKETS = ['niet vervallen', '1–30 dagen', '31–60 dagen', '61–90 dagen', 'meer dan 90 dagen'];

    public function index(): View
    {
        return view('reports.index');
    }

    /** Openstaande debiteuren (verkoop) of crediteuren (inkoop) op een peildatum, met ouderdom. */
    public function open(Request $request, string $soort): View|StreamedResponse
    {
        abort_unless(in_array($soort, ['debiteuren', 'crediteuren'], true), 404);
        $this->authorize($soort === 'debiteuren' ? 'facturen.bekijken' : 'inkoop.bekijken');
        $on = $this->date($request->query('peildatum'));
        $rows = $soort === 'debiteuren' ? $this->openInvoices($on) : $this->openPurchaseInvoices($on);
        $perParty = $rows->groupBy('party')->map(function (Collection $g, $party) {
            $b = array_fill_keys(self::BUCKETS, 0.0);
            foreach ($g as $r) {
                $b[$r['bucket']] += $r['open'];
            }

            return ['party' => $party, 'count' => $g->count(), 'open' => round($g->sum('open'), 2), 'buckets' => array_map(fn ($v) => round($v, 2), $b)];
        })->sortByDesc('open')->values();

        if ($request->query('export') === 'csv') {
            return Csv::download("{$soort}-{$on->toDateString()}.csv",
                ['Relatie', 'Nummer', 'Datum', 'Vervaldatum', 'Dagen vervallen', 'Totaal incl.', 'Openstaand', 'Ouderdom'],
                $rows->map(fn ($r) => [$r['party'], $r['number'], $r['date'], $r['due'], $r['days'], $r['total'], $r['open'], $r['bucket']]));
        }

        return view('reports.open', ['soort' => $soort, 'on' => $on, 'rows' => $rows, 'perParty' => $perParty, 'buckets' => self::BUCKETS,
            'total' => round($rows->sum('open'), 2), 'bucketTotals' => collect(self::BUCKETS)->mapWithKeys(fn ($b) => [$b => round($rows->where('bucket', $b)->sum('open'), 2)])]);
    }

    /** Omzet en inkoop per maand (definitieve verkoopfacturen en inkoopfacturen, excl. btw). */
    public function revenue(Request $request): View|StreamedResponse
    {
        $year = (int) $request->query('jaar', now()->year);
        $sales = $this->perMonth(Invoice::query()->where('status', '!=', 'concept'), $year);
        $purchases = $this->perMonth(PurchaseInvoice::query()->where('phase', '!=', 'concept'), $year);
        $months = collect(range(1, 12))->map(fn ($m) => [
            'month' => $m, 'label' => ucfirst(CarbonImmutable::create($year, $m, 1)->translatedFormat('F')),
            'sales' => $sales[$m] ?? 0.0, 'purchases' => $purchases[$m] ?? 0.0, 'result' => round(($sales[$m] ?? 0) - ($purchases[$m] ?? 0), 2),
        ]);
        if ($request->query('export') === 'csv') {
            return Csv::download("omzet-{$year}.csv", ['Maand', 'Omzet excl.', 'Inkoop excl.', 'Verschil'], $months->map(fn ($m) => [$m['label'], $m['sales'], $m['purchases'], $m['result']]));
        }
        $years = Invoice::query()->whereNotNull('date')->selectRaw($this->yearExpr().' as y')->distinct()->pluck('y')->map(fn ($y) => (int) $y)->sortDesc()->values();

        return view('reports.revenue', ['year' => $year, 'months' => $months, 'years' => $years->isEmpty() ? collect([$year]) : $years]);
    }

    /** Pijplijn: open offertes per fase met totaal en gewogen waarde. */
    public function pipeline(Request $request): View|StreamedResponse
    {
        $offers = Offer::query()->whereNull('acceptance')->where('archived', false)->where('is_template', false)->with(['company', 'phase'])->orderByDesc('total_excl')->get();
        $phases = OfferPhase::query()->orderBy('sort')->get()->map(fn ($p) => [
            'name' => $p->name, 'count' => $offers->where('phase_id', $p->id)->count(),
            'total' => round($offers->where('phase_id', $p->id)->sum('total_excl'), 2),
            'weighted' => round($offers->where('phase_id', $p->id)->sum(fn ($o) => $o->weightedValue()), 2),
        ]);
        if ($request->query('export') === 'csv') {
            return Csv::download('pijplijn.csv', ['Nummer', 'Omschrijving', 'Relatie', 'Fase', 'Status', 'Kans', 'Totaal excl.', 'Gewogen'],
                $offers->map(fn ($o) => [$o->number, $o->name, $o->company?->name, $o->phase?->name, $o->stateLabel(), $o->chance, $o->total_excl, $o->weightedValue()]));
        }

        return view('reports.pipeline', ['offers' => $offers, 'phases' => $phases]);
    }

    /** Uren per medewerker per maand. */
    public function hours(Request $request): View|StreamedResponse
    {
        $year = (int) $request->query('jaar', now()->year);
        $rows = Hour::query()->with('employee')->whereYear('date', $year)->get()
            ->groupBy('employee_id')->map(function ($g) {
                $months = array_fill(1, 12, 0.0);
                foreach ($g as $h) {
                    $months[(int) $h->date->format('n')] += (float) $h->hours;
                }

                return ['name' => $g->first()->employee?->name ?? '—', 'months' => $months, 'total' => array_sum($months), 'approved' => $g->where('status', 'gefiatteerd')->sum('hours')];
            })->sortBy('name')->values();
        if ($request->query('export') === 'csv') {
            return Csv::download("uren-{$year}.csv", array_merge(['Medewerker'], array_map(fn ($m) => (string) $m, range(1, 12)), ['Totaal', 'Gefiatteerd']),
                $rows->map(fn ($r) => array_merge([$r['name']], array_values($r['months']), [$r['total'], $r['approved']])));
        }

        return view('reports.hours', ['year' => $year, 'rows' => $rows]);
    }

    private function openInvoices(CarbonImmutable $on): Collection
    {
        return Invoice::query()->with('company')->where('status', '!=', 'concept')->whereDate('date', '<=', $on)
            ->withSum(['payments as paid_on' => fn ($q) => $q->whereDate('date', '<=', $on)], 'amount')->get()
            ->map(fn (Invoice $i) => $this->openRow($i->company?->name ?? '—', $i->number, $i->date, $i->due_date, (float) $i->total_incl, (float) $i->paid_on, $on))
            ->filter(fn ($r) => abs($r['open']) >= 0.005)->sortBy([['party', 'asc'], ['due', 'asc']])->values();
    }

    private function openPurchaseInvoices(CarbonImmutable $on): Collection
    {
        return PurchaseInvoice::query()->with('supplier')->where('phase', '!=', 'concept')->whereDate('date', '<=', $on)
            ->withSum(['payments as paid_on' => fn ($q) => $q->whereDate('date', '<=', $on)], 'amount')->get()
            ->map(fn (PurchaseInvoice $i) => $this->openRow($i->supplier?->name ?? '—', $i->booking_number.($i->invoice_number ? ' / '.$i->invoice_number : ''), $i->date, $i->due_date, (float) $i->total_incl, (float) $i->paid_on, $on))
            ->filter(fn ($r) => abs($r['open']) >= 0.005)->sortBy([['party', 'asc'], ['due', 'asc']])->values();
    }

    private function openRow(string $party, mixed $number, $date, $due, float $total, float $paid, CarbonImmutable $on): array
    {
        $days = $due ? (int) CarbonImmutable::parse($due)->startOfDay()->diffInDays($on->startOfDay(), false) : 0;
        $bucket = match (true) {
            $days <= 0 => self::BUCKETS[0], $days <= 30 => self::BUCKETS[1], $days <= 60 => self::BUCKETS[2], $days <= 90 => self::BUCKETS[3], default => self::BUCKETS[4],
        };

        return ['party' => $party, 'number' => $number, 'date' => $date, 'due' => $due, 'days' => max(0, $days), 'total' => round($total, 2), 'open' => round($total - $paid, 2), 'bucket' => $bucket];
    }

    /** @return array<int, float> maand => som excl. */
    private function perMonth($query, int $year): array
    {
        return $query->whereYear('date', $year)->get(['date', 'total_excl'])
            ->groupBy(fn ($r) => (int) $r->date->format('n'))->map(fn ($g) => round($g->sum('total_excl'), 2))->all();
    }

    private function yearExpr(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y', date)",
            'pgsql' => 'EXTRACT(YEAR FROM date)',
            default => 'YEAR(date)',
        };
    }

    private function date(?string $v): CarbonImmutable
    {
        try {
            return $v ? CarbonImmutable::parse($v) : CarbonImmutable::today();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }
}
