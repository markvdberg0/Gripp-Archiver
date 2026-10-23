<?php

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Import\Importer;
use App\Import\Snapshot;
use App\Jobs\RunImport;
use App\Models\ImportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ImportController extends Controller
{
    public function index(): View
    {
        $path = config('werkbank.import_path');
        $snapshot = null;
        $problem = null;
        if ($path) {
            try {
                $s = new Snapshot($path);
                $snapshot = ['batch' => $s->batch(), 'status' => $s->status(), 'dir' => $s->dataDir];
            } catch (Throwable $e) {
                $problem = $e->getMessage();
            }
        }

        return view('beheer.import.index', [
            'path' => $path,
            'snapshot' => $snapshot,
            'problem' => $problem,
            'entities' => Importer::entities(),
            'runs' => ImportRun::with('user')->latest()->limit(30)->get(),
            'running' => ImportRun::whereIn('status', ['wachtrij', 'bezig'])->where('created_at', '>', now()->subHours(2))->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:droogloop,import'],
            'only' => ['nullable', 'array'],
            'only.*' => ['in:'.implode(',', Importer::entities())],
        ]);
        abort_unless(config('werkbank.import_path'), 422, 'WERKBANK_IMPORT_PATH is niet ingesteld.');
        if (ImportRun::whereIn('status', ['wachtrij', 'bezig'])->where('created_at', '>', now()->subHours(2))->exists()) {
            return back()->withErrors(['mode' => 'Er loopt al een import. Wacht tot die klaar is.']);
        }
        $run = ImportRun::create([
            'user_id' => $request->user()->id,
            'source_path' => config('werkbank.import_path'),
            'snapshot' => 'current',
            'dry_run' => $data['mode'] === 'droogloop',
            'only' => ! empty($data['only']) ? implode(',', $data['only']) : null,
        ]);
        RunImport::dispatch($run);

        return redirect()->route('beheer.import.show', $run)->with('status', $run->dry_run ? 'Droogloop gestart.' : 'Import gestart.');
    }

    public function show(ImportRun $run): View
    {
        return view('beheer.import.show', ['run' => $run->load('user')]);
    }
}
