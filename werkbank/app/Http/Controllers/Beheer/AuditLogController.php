<?php

namespace App\Http\Controllers\Beheer;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = AuditLog::query()->with('user')
            ->when($request->query('gebruiker'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('gebeurtenis'), fn ($q, $e) => $q->where('event', $e))
            ->when($request->query('type'), fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($request->query('id'), fn ($q, $id) => $q->where('auditable_id', $id))
            ->latest('id')->paginate(100)->withQueryString();

        return view('beheer.logboek', [
            'logs' => $logs,
            'users' => User::orderBy('name')->pluck('name', 'id'),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event', 'event'),
        ]);
    }
}
