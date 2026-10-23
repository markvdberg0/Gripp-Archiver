<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Offer;
use App\Models\OfferPhase;
use App\Models\Packet;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\VatRate;
use App\Support\Audit;
use App\Support\Nummering;
use App\Support\Regels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfferController extends Controller
{
    private const SORT = ['number' => 'number', 'date' => 'date', 'name' => 'name', 'total' => 'total_excl'];

    public function index(Request $request): View
    {
        $sort = self::SORT[$request->query('sort')] ?? 'number';
        $dir = $request->query('dir', $request->has('sort') ? 'asc' : 'desc') === 'desc' ? 'desc' : 'asc';
        $base = Offer::query()->search($request->query('q'))->state($request->query('status'))
            ->when($request->query('fase'), fn ($q, $f) => $q->where('phase_id', $f))
            ->when($request->query('relatie'), fn ($q, $c) => $q->where('company_id', $c))
            ->when($request->query('accountmanager'), fn ($q, $e) => $q->where('account_manager_id', $e))
            ->when($request->query('archief') !== '1', fn ($q) => $q->where('archived', false))
            ->where('is_template', $request->query('sjablonen') === '1');
        $sum = (clone $base)->selectRaw('count(*) as n, coalesce(sum(total_excl),0) as total, coalesce(sum(total_excl * coalesce(chance,0) / 100),0) as weighted')->toBase()->first();
        $offers = $base->with(['company', 'phase', 'accountManager'])->orderBy($sort, $dir)->orderByDesc('id')->paginate(50)->withQueryString();

        return view('offers.index', [
            'offers' => $offers, 'sum' => $sum, 'phases' => OfferPhase::options(), 'employees' => Employee::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $company = $request->integer('company_id') ? Company::find($request->integer('company_id')) : null;
        $offer = new Offer([
            'company_id' => $company?->id, 'date' => now(), 'status' => 'concept', 'chance' => 50,
            'valid_until' => now()->addDays((int) Setting::get('offerte.geldigheid_dagen', '30')),
            'account_manager_id' => $company?->account_manager_id ?? $request->user()->employee?->id,
            'phase_id' => OfferPhase::query()->orderBy('sort')->value('id'),
        ]);

        return view('offers.form', $this->formData($offer) + ['nextNumber' => Nummering::peek('offerte', 'offers')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $offer = DB::transaction(function () use ($data) {
            $template = (bool) ($data['is_template'] ?? false);
            $offer = Offer::create(Arr::except($data, ['lines', 'packet_id']) + ['number' => $template ? null : Nummering::next('offerte', 'offers'), 'status' => 'concept']);
            Regels::sync($offer, $data['lines'] ?? []);
            $this->addPacket($offer, $data['packet_id'] ?? null);
            $offer->recalculate();

            return $offer;
        });

        return redirect()->route('offertes.show', $offer)->with('status', "Offerte {$offer->number} aangemaakt.");
    }

    public function show(Offer $offerte): View
    {
        $offerte->load(['company', 'contact', 'phase', 'accountManager', 'files', 'tags', 'lines.unit', 'lines.vatRate', 'lines.product']);
        $timeline = $offerte->timeline()->with(['employee', 'user', 'files'])->paginate(25);

        return view('offers.show', ['offer' => $offerte, 'timeline' => $timeline, 'vat' => $offerte->vatBreakdown(), 'related' => $this->related($offerte)]);
    }

    /** Documenten uit latere fasen (opdracht). */
    protected function related(Offer $offer): array
    {
        return method_exists($offer, 'projects') ? ['projects' => $offer->projects()->get()] : [];
    }

    public function edit(Offer $offerte): View|RedirectResponse
    {
        if (! $offerte->isEditable()) {
            return redirect()->route('offertes.show', $offerte)->withErrors(['offerte' => 'Een geaccepteerde, afgewezen of gearchiveerde offerte kan niet meer worden gewijzigd. Kopieer hem om een nieuwe versie te maken.']);
        }

        return view('offers.form', $this->formData($offerte->load('lines')));
    }

    public function update(Request $request, Offer $offerte): RedirectResponse
    {
        abort_unless($offerte->isEditable(), 422, 'Deze offerte kan niet meer worden gewijzigd.');
        $data = $this->validated($request);
        DB::transaction(function () use ($offerte, $data) {
            $offerte->update(Arr::except($data, ['lines', 'packet_id', 'is_template']));
            Regels::sync($offerte, $data['lines'] ?? []);
            $this->addPacket($offerte, $data['packet_id'] ?? null);
            $offerte->recalculate();
        });

        return redirect()->route('offertes.show', $offerte)->with('status', 'Offerte opgeslagen.');
    }

    public function destroy(Offer $offerte): RedirectResponse
    {
        if ($offerte->status !== 'concept' || $offerte->acceptance) {
            return back()->withErrors(['offerte' => 'Alleen een conceptofferte kan worden verwijderd. Archiveer verzonden offertes.']);
        }
        $offerte->delete();

        return redirect()->route('offertes.index')->with('status', "Offerte {$offerte->number} verwijderd.");
    }

    /** Statusovergangen: verzonden, geaccepteerd, afgewezen, heropenen, archiveren. */
    public function transition(Request $request, Offer $offerte, string $actie): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:255']]);
        abort_if($offerte->is_template, 422, 'Een sjabloon heeft geen status.');
        $before = $offerte->state();
        switch ($actie) {
            case 'verzonden':
                abort_unless($offerte->status === 'concept' && ! $offerte->acceptance, 422);
                $offerte->update(['status' => 'verzonden']);
                break;
            case 'geaccepteerd':
                abort_if($offerte->acceptance !== null || $offerte->archived, 422);
                $offerte->update(['status' => 'verzonden', 'acceptance' => 'geaccepteerd', 'accepted_at' => now(), 'chance' => 100]);
                break;
            case 'afgewezen':
                abort_if($offerte->acceptance !== null || $offerte->archived, 422);
                $offerte->update(['acceptance' => 'afgewezen', 'rejection_reason' => $data['rejection_reason'] ?? null, 'chance' => 0]);
                break;
            case 'heropenen':
                abort_if($offerte->acceptance === null && ! $offerte->archived, 422);
                abort_if(method_exists($offerte, 'projects') && $offerte->projects()->exists(), 422, 'Er is al een opdracht van deze offerte gemaakt.');
                $offerte->update(['acceptance' => null, 'accepted_at' => null, 'rejection_reason' => null, 'archived' => false, 'archived_at' => null]);
                break;
            case 'archiveren':
                $offerte->update(['archived' => true, 'archived_at' => now()]);
                break;
            default:
                abort(404);
        }
        $offerte->timeline()->create(['type' => 'systeem', 'body' => "Status gewijzigd van {$before} naar ".$offerte->state().($offerte->archived ? ' (gearchiveerd)' : '').'.', 'occurred_at' => now(), 'user_id' => $request->user()->id, 'company_id' => $offerte->company_id]);

        return back()->with('status', 'Offerte bijgewerkt: '.mb_strtolower($offerte->stateLabel()).($offerte->archived ? ', gearchiveerd' : '').'.');
    }

    /** Nieuwe conceptofferte op basis van deze (nieuwe versie of hergebruik). */
    public function copy(Offer $offerte): RedirectResponse
    {
        $copy = DB::transaction(function () use ($offerte) {
            $copy = $offerte->replicate(['gripp_id', 'gripp_hash', 'gripp_synced_at', 'number', 'status', 'acceptance', 'accepted_at', 'rejection_reason', 'archived', 'archived_at', 'total_excl', 'total_vat', 'total_incl']);
            $copy->fill(['number' => Nummering::next('offerte', 'offers'), 'is_template' => false, 'status' => 'concept', 'date' => now(), 'name' => $offerte->name,
                'valid_until' => now()->addDays((int) Setting::get('offerte.geldigheid_dagen', '30'))])->save();
            Regels::copy($offerte, $copy);
            $copy->recalculate();

            return $copy;
        });

        return redirect()->route('offertes.edit', $copy)->with('status', "Kopie gemaakt als offerte {$copy->number}.");
    }

    public function pdf(Offer $offerte): Response
    {
        $offerte->load(['company', 'contact', 'accountManager', 'lines.unit', 'lines.vatRate', 'lines.product']);
        Audit::log('pdf', $offerte, $offerte->auditLabel());

        return Pdf::loadView('pdf.offer', ['offer' => $offerte, 'vat' => $offerte->vatBreakdown()])
            ->setPaper('a4')
            ->stream("Offerte {$offerte->number}.pdf");
    }

    private function addPacket(Offer $offer, ?int $packetId): void
    {
        if (! $packetId) {
            return;
        }
        $packet = Packet::with('lines')->findOrFail($packetId);
        $sort = (int) $offer->lines()->max('sort') + 1;
        $offer->lines()->create(['sort' => $sort++, 'row_type' => 'groep', 'title' => $packet->name, 'quantity' => 0]);
        foreach ($packet->lines as $line) {
            $offer->lines()->create(['sort' => $sort++] + $line->only(['row_type', 'product_id', 'title', 'description', 'quantity', 'unit_id', 'unit_price', 'buying_price', 'discount', 'vat_rate_id']));
        }
        $offer->unsetRelation('lines');
    }

    private function formData(Offer $offer): array
    {
        return [
            'offer' => $offer,
            'companies' => Company::options(),
            'contacts' => Contact::query()->where('active', true)->when($offer->company_id, fn ($q) => $q->where('company_id', $offer->company_id))->orderBy('last_name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->name])->all(),
            'phases' => OfferPhase::options(),
            'employees' => Employee::options(),
            'products' => Product::forPicker(),
            'units' => Unit::options(),
            'vatRates' => VatRate::options('verkoop'),
            'packets' => Packet::where('archived', false)->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    private function validated(Request $request): array
    {
        Regels::prepare($request);

        return $request->validate(Regels::rules([
            'name' => ['required', 'string', 'max:255'],
            'is_template' => ['boolean'],
            'company_id' => [$request->boolean('is_template') ? 'nullable' : 'required', 'exists:companies,id'],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('company_id', $request->input('company_id'))],
            'phase_id' => ['nullable', 'exists:offer_phases,id'],
            'account_manager_id' => ['nullable', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:date'],
            'deadline' => ['nullable', 'date'],
            'chance' => ['nullable', 'integer', 'between:0,100'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:100000'],
            'packet_id' => ['nullable', 'exists:packets,id'],
        ]), [
            'contact_id.exists' => 'De contactpersoon hoort niet bij de gekozen relatie.',
        ], Regels::attributes() + ['name' => 'omschrijving', 'valid_until' => 'geldig tot', 'chance' => 'kans']);
    }
}
