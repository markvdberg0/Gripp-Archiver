<?php

namespace App\Http\Controllers;

use App\Models\StoredFile;
use App\Support\Attachables;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /** Downloaden of (PDF, afbeelding) tonen. Alleen als je minstens één record mag zien waar het bestand aan hangt. */
    public function show(Request $request, StoredFile $bestand): StreamedResponse
    {
        abort_unless($this->mayView($request, $bestand), 403);
        abort_unless($bestand->hasContent(), 404, 'De inhoud van dit bestand ontbreekt.');
        $inline = in_array($bestand->mime_type, ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'text/plain'], true) && ! $request->boolean('download');

        return Storage::disk('local')->response($bestand->path, $bestand->name, [
            'Content-Type' => $bestand->mime_type ?? 'application/octet-stream',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Attachables::morphMap()))],
            'id' => ['required', 'integer'],
            'bestand' => ['required', 'file', 'max:'.config('werkbank.max_upload_kb')],
        ]);
        [$class, , $upload] = Attachables::MAP[$data['type']];
        $this->authorize($upload);
        $model = $class::findOrFail($data['id']);
        $upload = $request->file('bestand');

        $file = DB::transaction(function () use ($upload, $request, $model) {
            $stored = StoredFile::storeContent($upload->getRealPath());
            $file = StoredFile::create($stored + [
                'name' => mb_substr($upload->getClientOriginalName() ?: 'bestand', 0, 255),
                'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                'uploaded_by' => $request->user()->id,
            ]);
            $model->files()->attach($file->id, ['role' => 'bijlage', 'created_at' => now()]);

            return $file;
        });
        Audit::log('bestand_toegevoegd', $model, $file->name);

        return back()->with('status', "Bestand {$file->name} toegevoegd.");
    }

    private function mayView(Request $request, StoredFile $file): bool
    {
        $links = DB::table('attachments')->where('file_id', $file->id)->get(['attachable_type', 'attachable_id']);
        foreach ($links as $l) {
            $view = Attachables::MAP[$l->attachable_type][1] ?? null;
            if ($view && $request->user()->can($view)) {
                return true;
            }
        }

        // Niet gekoppelde bestanden (bv. alleen geïmporteerd) mag een beheerder zien.
        return $links->isEmpty() && $request->user()->can('beheer.import');
    }
}
