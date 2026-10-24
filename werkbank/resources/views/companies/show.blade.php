@extends('layouts.app', ['title' => $company->name])
@php use App\Support\Format; @endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $company->name }}</h1>
        <div class="sub">
            Relatie {{ $company->number }} · {{ $company->relation_type === 'particulier' ? 'Particulier' : 'Bedrijf' }}
            @foreach ($company->roleLabels() as $r) <span class="badge badge-accent">{{ $r }}</span>@endforeach
            @unless ($company->active) <span class="badge">inactief</span>@endunless
            @foreach ($company->tags as $tag) <span class="badge">{{ $tag->name }}</span>@endforeach
        </div>
    </div>
    <div class="actions">
        @can('offertes.bewerken')@if (Route::has('offertes.create'))<a class="btn" href="{{ route('offertes.create', ['company_id' => $company->id]) }}">Nieuwe offerte</a>@endif @endcan
        @can('relaties.bewerken')
            <a class="btn" href="{{ route('contactpersonen.create', ['company_id' => $company->id]) }}">Contactpersoon toevoegen</a>
            <a class="btn btn-primary" href="{{ route('relaties.edit', $company) }}">Wijzigen</a>
        @endcan
        @can('relaties.samenvoegen')<a class="btn" href="{{ route('relaties.samenvoegen', $company) }}">Samenvoegen…</a>@endcan
    </div>
</div>

<div class="grid grid-side">
    <div>
        <div class="card">
            <div class="card-h"><h2>Gegevens</h2></div>
            <div class="card-b">
                <dl class="props">
                    @if ($company->legal_name)<dt>Statutaire naam</dt><dd>{{ $company->legal_name }}</dd>@endif
                    <dt>Bezoekadres</dt><dd>{{ $company->visitAddress() ?: '—' }} {{ $company->visit_country && $company->visit_country !== 'Nederland' ? '('.$company->visit_country.')' : '' }}</dd>
                    @if ($company->postal_street || $company->postal_city)<dt>Postadres</dt><dd>{{ \App\Models\Company::address($company->postal_street, $company->postal_house_number, $company->postal_postcode, $company->postal_city) }}</dd>@endif
                    <dt>E-mail</dt><dd>@if ($company->email)<a href="mailto:{{ $company->email }}">{{ $company->email }}</a>@else — @endif</dd>
                    <dt>Telefoon</dt><dd>{{ $company->phone ?: '—' }} {{ $company->mobile ? ' · '.$company->mobile : '' }}</dd>
                    @if ($company->website)<dt>Website</dt><dd>{{ $company->website }}</dd>@endif
                    <dt>KvK / btw</dt><dd>{{ $company->coc_number ?: '—' }} / {{ $company->vat_number ?: '—' }} {{ $company->vat_shifted ? '(btw verlegd)' : '' }}</dd>
                    <dt>Accountmanager</dt><dd>{{ $company->accountManager?->name ?? '—' }}</dd>
                    <dt>Facturen</dt><dd>per {{ $company->invoice_send_by === 'post' ? 'post' : 'e-mail' }}{{ $company->invoice_email ? ' naar '.$company->invoice_email : '' }} · {{ \App\Models\Company::BETAALWIJZEN[$company->payment_method] ?? $company->payment_method }}{{ $company->payment_term_days ? ' · '.$company->payment_term_days.' dagen' : '' }}</dd>
                    @if ($company->invoice_street || $company->invoice_company_name)<dt>Factuuradres</dt><dd>{{ $company->invoice_company_name }}{{ $company->invoice_attention ? ', t.a.v. '.$company->invoice_attention : '' }} {{ \App\Models\Company::address($company->invoice_street, $company->invoice_house_number, $company->invoice_postcode, $company->invoice_city) }}</dd>@endif
                    @can('relaties.financieel')
                        <dt>Bank</dt><dd>{{ $company->iban ?: '—' }} {{ $company->bic }} {{ $company->bank_account_holder ? 't.n.v. '.$company->bank_account_holder : '' }}</dd>
                    @endcan
                    @if ($company->notes)<dt>Notities</dt><dd>{!! nl2br(e($company->notes)) !!}</dd>@endif
                </dl>
            </div>
        </div>

        @foreach ($related as $rel => $items)
            @include('companies.related.'.$rel, ['items' => $items, 'company' => $company])
        @endforeach

        <div class="card">
            <div class="card-h"><h2>Timeline</h2><span class="muted small">{{ $timeline->total() }} items</span></div>
            @can('relaties.bewerken')
            <div class="card-b" style="border-bottom:1px solid var(--line)">
                <form method="post" action="{{ route('relaties.notities.store', $company) }}">
                    @csrf
                    <div class="form-grid">
                        <x-keuze name="type" label="Soort" :options="array_intersect_key(\App\Models\TimelineEntry::TYPES, array_flip(['notitie', 'telefoon', 'email_ontvangen', 'email_verzonden']))" value="notitie" />
                        <x-keuze name="contact_id" label="Contactpersoon" :options="$company->contacts->pluck('name', 'id')" placeholder="—" />
                        <x-veld name="subject" label="Onderwerp" wrap="span-2" />
                        <x-tekst name="body" label="Notitie" required wrap="span-2" rows="3" />
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">Toevoegen</button>
                </form>
            </div>
            @endcan
            @include('partials.timeline', ['entries' => $timeline])
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-h"><h2>Contactpersonen</h2><span class="muted small">{{ $company->contacts->count() }}</span></div>
            <div class="card-b flush">
                @forelse ($company->contacts as $contact)
                    <div style="padding:10px 16px;border-bottom:1px solid var(--line)">
                        <a href="{{ route('contactpersonen.show', $contact) }}">{{ $contact->name }}</a>
                        @unless ($contact->active)<span class="badge">inactief</span>@endunless
                        <div class="muted small">{{ $contact->function }}</div>
                        <div class="small">{{ $contact->email }} {{ $contact->phone ?: $contact->mobile }}</div>
                    </div>
                @empty
                    <div class="empty">Nog geen contactpersonen.</div>
                @endforelse
            </div>
        </div>
        @include('partials.bestanden', ['model' => $company, 'type' => 'company', 'canUpload' => auth()->user()->can('relaties.bewerken')])
        <div class="card"><div class="card-b small muted">
            Aangemaakt {{ Format::dateTime($company->created_at) }} · gewijzigd {{ Format::dateTime($company->updated_at) }}
            @if ($company->gripp_id)<br>Gripp-id {{ $company->gripp_id }}, laatst geïmporteerd {{ Format::dateTime($company->gripp_synced_at) }}@if ($company->isLocallyModified()) · <span class="badge badge-warn">lokaal gewijzigd</span>@endif @endif
        </div></div>
        @can('relaties.bewerken')
        <form method="post" action="{{ route('relaties.destroy', $company) }}" data-confirm="Relatie verwijderen?" class="no-print">
            @csrf @method('delete')
            <button class="btn btn-danger btn-sm" type="submit">Relatie verwijderen</button>
        </form>
        @endcan
    </div>
</div>
@endsection
