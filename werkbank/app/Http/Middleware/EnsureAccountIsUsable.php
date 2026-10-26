<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Een gedeactiveerde gebruiker wordt direct uitgelogd. Moet het wachtwoord gewijzigd worden
 * (nieuw account of na een reset door de beheerder), dan kan alleen dat.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Je account is niet (meer) actief.']);
        }
        if ($user && $user->must_change_password && ! $request->routeIs('account.*', 'logout')) {
            return redirect()->route('account.edit')->with('warning', 'Kies eerst een eigen wachtwoord.');
        }
        if ($user && ! $user->hasTwoFactor() && Setting::get('beveiliging.tweestaps_verplicht') && ! $request->routeIs('account.*', 'logout')) {
            return redirect()->route('account.tweestaps')->with('warning', 'Stel eerst tweestapsverificatie in; dat is verplicht gesteld door de beheerder.');
        }

        return $next($request);
    }
}
