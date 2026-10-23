<?php

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Backup;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Back-ups van Werkbank bekijken, maken en downloaden. Terugzetten gaat bewust alleen via de opdrachtregel
 * (php artisan werkbank:herstel), omdat het alle gegevens vervangt.
 */
class BackupController extends Controller
{
    public function index(): View
    {
        return view('beheer.backups', ['backups' => Backup::list(), 'dir' => Backup::directory(), 'keep' => config('werkbank.backup_keep')]);
    }

    public function store(): RedirectResponse
    {
        $path = Backup::create();
        Backup::verify($path);

        return back()->with('status', 'Back-up gemaakt en gecontroleerd: '.basename($path));
    }

    public function download(string $name): BinaryFileResponse
    {
        $match = collect(Backup::list())->firstWhere('name', basename($name));
        abort_unless($match && $match['name'] === $name, 404);
        Audit::log('backup_gedownload', null, $name);

        return response()->download($match['path'], $name, ['Content-Type' => 'application/zip']);
    }
}
