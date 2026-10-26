<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Wachtwoord vergeten: link per e-mail. De melding is altijd dezelfde, zodat niet te achterhalen is of een
 * e-mailadres een account heeft.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $user = User::where('email', $request->input('email'))->where('active', true)->first();
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
            Audit::log('wachtwoordherstel_gevraagd', $user, $user->email);
        }

        return back()->with('status', 'Als dit e-mailadres bij een actief account hoort, is er een e-mail verstuurd met een link om een nieuw wachtwoord te kiezen.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        $status = Password::reset($data + ['password_confirmation' => $request->input('password_confirmation')], function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'must_change_password' => false, 'remember_token' => Str::random(60)])->saveQuietly();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Audit::log('wachtwoord_hersteld', $user, $user->email);
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Deze link is ongeldig of verlopen. Vraag een nieuwe aan.']);
        }

        return redirect()->route('login')->with('status', 'Je wachtwoord is gewijzigd. Log in met je nieuwe wachtwoord.');
    }
}
