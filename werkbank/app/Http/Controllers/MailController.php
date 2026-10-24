<?php

namespace App\Http\Controllers;

use App\Mail\DocumentMail;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Models\StoredFile;
use App\Support\Audit;
use App\Support\Documenten;
use App\Support\Format;
use App\Support\Ubl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Documenten per e-mail versturen, met de PDF als bijlage. De verzonden PDF en de tekst worden in de
 * timeline bewaard, zodat altijd terug te zien is wat er is verstuurd.
 */
class MailController extends Controller
{
    /** soort => [model, recht, route-naam van het document, timeline-kolom] */
    private const TYPES = [
        'offerte' => [Offer::class, 'offertes.bewerken', 'offertes.show', 'offer_id'],
        'factuur' => [Invoice::class, 'facturen.bewerken', 'facturen.show', 'invoice_id'],
        'herinnering' => [Invoice::class, 'facturen.bewerken', 'facturen.show', 'invoice_id'],
        'opdracht' => [Project::class, 'opdrachten.bewerken', 'opdrachten.show', 'project_id'],
        'inkoopopdracht' => [PurchaseOrder::class, 'inkoop.bewerken', 'inkoopopdrachten.show', 'purchase_order_id'],
    ];

    public function create(Request $request, string $soort, int $id): View
    {
        $doc = $this->document($soort, $id);
        $this->guard($soort, $doc);
        $preview = $soort === 'herinnering' ? $doc->replicate()->forceFill(['reminder_level' => min(3, $doc->reminder_level + 1)]) : $doc;

        return view('mail.form', [
            'soort' => $soort, 'doc' => $doc, 'back' => route(self::TYPES[$soort][2], $doc),
            'to' => $this->recipient($doc), 'subject' => $this->fill(Setting::get("mail.{$soort}.onderwerp"), $preview, $request),
            'body' => $this->fill(Setting::get("mail.{$soort}.tekst"), $preview, $request),
            'post' => $doc instanceof Invoice && $doc->company?->invoice_send_by === 'post',
        ]);
    }

    public function send(Request $request, string $soort, int $id): RedirectResponse
    {
        $doc = $this->document($soort, $id);
        $this->guard($soort, $doc);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:500'],
            'cc' => ['nullable', 'string', 'max:500'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'bcc_self' => ['boolean'],
            'ubl' => ['boolean'],
        ], [], ['to' => 'aan', 'subject' => 'onderwerp', 'body' => 'tekst']);
        $to = $this->addresses($data['to'], 'to');
        $cc = $this->addresses($data['cc'] ?? '', 'cc');
        $user = $request->user();

        try {
            DB::transaction(function () use ($soort, $doc, $data, $to, $cc, $user) {
                // Statusovergangen die bij versturen horen (vóór de PDF, zodat die de juiste stand toont).
                if ($soort === 'herinnering') {
                    $doc->update(['reminder_level' => min(3, $doc->reminder_level + 1), 'last_reminder_at' => now()]);
                } elseif ($doc instanceof Offer && $doc->status === 'concept') {
                    $doc->update(['status' => 'verzonden']);
                } elseif ($doc instanceof PurchaseOrder && $doc->status === 'concept') {
                    $doc->update(['status' => 'verzonden']);
                }
                $pdf = Documenten::pdf($doc);

                $mail = Mail::to($to);
                if ($cc) {
                    $mail->cc($cc);
                }
                if (! empty($data['bcc_self']) && $user->email) {
                    $mail->bcc($user->email);
                }
                $extra = [];
                if (! empty($data['ubl']) && $doc instanceof Invoice) {
                    $ubl = Ubl::invoice($doc);
                    $extra[$ubl['filename']] = [$ubl['xml'], 'application/xml'];
                }
                $mail->send(new DocumentMail($data['subject'], $data['body'], $pdf['bytes'], $pdf['filename'], $user->email, $user->name, $extra));

                $this->archive($doc, $soort, $data, $to, $cc, $pdf, $user);
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors(['to' => 'Versturen is mislukt: '.$e->getMessage().' Controleer de mailinstellingen (MAIL_* in .env). Er is niets gewijzigd.']);
        }
        Audit::log('email_verzonden', $doc, $data['subject'], ['aan' => implode(', ', $to)]);

        return redirect()->route(self::TYPES[$soort][2], $doc)->with('status', 'Verstuurd naar '.implode(', ', $to).'.');
    }

    private function archive(Model $doc, string $soort, array $data, array $to, array $cc, array $pdf, $user): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wb');
        file_put_contents($tmp, $pdf['bytes']);
        $file = StoredFile::create(StoredFile::storeContent($tmp) + ['name' => $pdf['filename'], 'mime_type' => 'application/pdf', 'uploaded_by' => $user->id]);
        @unlink($tmp);
        $company = $doc instanceof PurchaseOrder ? $doc->supplier_id : $doc->company_id;
        $entry = $doc->timeline()->create([
            'type' => 'email_verzonden', 'subject' => $data['subject'], 'occurred_at' => now(), 'user_id' => $user->id, 'employee_id' => $user->employee?->id,
            'company_id' => $company, 'contact_id' => $doc->contact_id ?? null,
            'body' => 'Aan: '.implode(', ', $to).($cc ? "\nCc: ".implode(', ', $cc) : '')."\n\n".$data['body'],
        ]);
        $entry->files()->attach($file->id, ['role' => 'bijlage', 'created_at' => now()]);
    }

    private function document(string $soort, int $id): Model
    {
        $class = self::TYPES[$soort][0] ?? abort(404);

        return $class::findOrFail($id);
    }

    private function guard(string $soort, Model $doc): void
    {
        $this->authorize(self::TYPES[$soort][1]);
        if ($doc instanceof Offer) {
            abort_if($doc->is_template, 422, 'Een sjabloon kan niet worden verstuurd.');
        }
        if ($soort === 'factuur') {
            abort_if($doc->isEditable(), 422, 'Maak de factuur eerst definitief.');
        }
        if ($soort === 'herinnering') {
            abort_unless($doc->isOverdue(), 422, 'Alleen vervallen, openstaande facturen krijgen een herinnering.');
        }
    }

    private function recipient(Model $doc): string
    {
        return match (true) {
            $doc instanceof Invoice => $doc->company?->invoice_email ?: ($doc->contact?->email ?: $doc->company?->email),
            $doc instanceof PurchaseOrder => $doc->contact?->email ?: $doc->supplier?->email,
            default => $doc->contact?->email ?: $doc->company?->email,
        } ?? '';
    }

    /** @return list<string> */
    private function addresses(string $input, string $field): array
    {
        $list = array_values(array_filter(array_map('trim', preg_split('/[,;]/', $input))));
        foreach ($list as $a) {
            if (! filter_var($a, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([$field => "{$a} is geen geldig e-mailadres."]);
            }
        }
        if (count($list) > 10) {
            throw ValidationException::withMessages([$field => 'Maximaal 10 adressen.']);
        }

        return $list;
    }

    private function fill(?string $template, Model $doc, Request $request): string
    {
        $party = $doc instanceof PurchaseOrder ? $doc->supplier : $doc->company;

        return strtr((string) $template, [
            '{nummer}' => (string) ($doc->number ?? ''),
            '{omschrijving}' => (string) ($doc->name ?? $doc->subject ?? ''),
            '{relatie}' => (string) ($party?->name ?? ''),
            '{contactpersoon}' => $doc->contact?->name ?: 'heer, mevrouw',
            '{bedrag}' => Format::euro($doc->total_incl ?? 0),
            '{openstaand}' => Format::euro($doc->total_open ?? 0),
            '{vervaldatum}' => Format::date($doc->due_date ?? null),
            '{bedrijf}' => (string) Setting::get('bedrijf.naam', config('app.name')),
            '{afzender}' => (string) $request->user()->name,
        ]);
    }
}
