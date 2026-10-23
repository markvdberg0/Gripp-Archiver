<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Rechten;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.edit', [
            'user' => $request->user(),
            'rechten' => Rechten::vanRol($request->user()->role),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);
        $user = $request->user();
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->saveQuietly();
        Audit::log('wachtwoord_gewijzigd', $user, $user->email);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Je wachtwoord is gewijzigd.');
    }
}
