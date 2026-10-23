<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\TimelineEntry;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $contacts = Contact::query()->search($request->query('q'))
            ->when($request->query('actief', '1') !== 'alle', fn ($q) => $q->where('active', $request->query('actief', '1') === '1'))
            ->with('company')->orderBy('last_name')->orderBy('first_name')
            ->paginate(50)->withQueryString();

        return view('contacts.index', compact('contacts'));
    }

    public function create(Request $request): View
    {
        $this->authorize('relaties.bewerken');

        return view('contacts.form', ['contact' => new Contact(['company_id' => $request->integer('company_id') ?: null, 'active' => true, 'show_on_company' => true]), 'companies' => Company::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('relaties.bewerken');
        $contact = Contact::create(Arr::except($this->validated($request), ['tags']));
        $contact->syncTagNames(explode(',', (string) $request->input('tags')));

        return redirect()->route('contactpersonen.show', $contact)->with('status', 'Contactpersoon aangemaakt.');
    }

    public function show(Contact $contactpersoon): View
    {
        $contactpersoon->load(['company', 'tags']);
        $timeline = TimelineEntry::where('contact_id', $contactpersoon->id)->with(['employee', 'user', 'files'])->latest('occurred_at')->paginate(25);

        return view('contacts.show', ['contact' => $contactpersoon, 'timeline' => $timeline]);
    }

    public function edit(Contact $contactpersoon): View
    {
        $this->authorize('relaties.bewerken');

        return view('contacts.form', ['contact' => $contactpersoon->load('tags'), 'companies' => Company::options()]);
    }

    public function update(Request $request, Contact $contactpersoon): RedirectResponse
    {
        $this->authorize('relaties.bewerken');
        $contactpersoon->update(Arr::except($this->validated($request), ['tags']));
        $contactpersoon->syncTagNames(explode(',', (string) $request->input('tags')));

        return redirect()->route('contactpersonen.show', $contactpersoon)->with('status', 'Contactpersoon opgeslagen.');
    }

    public function destroy(Contact $contactpersoon): RedirectResponse
    {
        $this->authorize('relaties.bewerken');
        $company = $contactpersoon->company;
        $contactpersoon->delete();

        return $company ? redirect()->route('relaties.show', $company)->with('status', 'Contactpersoon verwijderd.') : redirect()->route('contactpersonen.index')->with('status', 'Contactpersoon verwijderd.');
    }

    /**
     * AVG: persoonsgegevens van een contactpersoon definitief wissen. Het record blijft bestaan (voor
     * koppelingen met documenten), maar naam en contactgegevens worden vervangen en de oude waarden
     * verdwijnen ook uit het logboek.
     */
    public function anonymize(Contact $contactpersoon): RedirectResponse
    {
        $this->authorize('beheer.gebruikers');
        DB::transaction(function () use ($contactpersoon) {
            Audit::$muted = true;
            $contactpersoon->forceFill([
                'salutation' => null, 'initials' => null, 'first_name' => null, 'infix' => null, 'last_name' => 'Geanonimiseerd',
                'function' => null, 'department' => null, 'email' => null, 'phone' => null, 'mobile' => null, 'notes' => null, 'active' => false,
            ])->save();
            $contactpersoon->tags()->detach();
            Audit::$muted = false;
            DB::table('audit_logs')->where('auditable_type', $contactpersoon->getMorphClass())->where('auditable_id', $contactpersoon->id)
                ->update(['changes' => null, 'description' => 'Contactpersoon (geanonimiseerd)']);
            Audit::log('geanonimiseerd', $contactpersoon, 'Contactpersoon #'.$contactpersoon->id);
        });

        return redirect()->route('contactpersonen.show', $contactpersoon)->with('status', 'Persoonsgegevens gewist. Notities in de timeline zijn niet aangepast; controleer die zo nodig.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'salutation' => ['nullable', Rule::in(array_keys(Contact::AANHEF))],
            'initials' => ['nullable', 'string', 'max:20'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'infix' => ['nullable', 'string', 'max:30'],
            'last_name' => ['required', 'string', 'max:100'],
            'function' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'mobile' => ['nullable', 'string', 'max:40'],
            'show_on_company' => ['boolean'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'tags' => ['nullable', 'string', 'max:500'],
        ], [], ['last_name' => 'achternaam', 'company_id' => 'relatie']);
    }
}
