<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Tweestapsverificatie van de eigen account instellen, herstelcodes vernieuwen en uitzetten. */
class TwoFactorController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $secret = null;
        if (! $user->hasTwoFactor()) {
            // Het geheim staat tot de bevestiging alleen in de sessie.
            $secret = $request->session()->get('tweestaps.geheim') ?? tap(Totp::secret(), fn ($s) => $request->session()->put('tweestaps.geheim', $s));
        }
        $uri = $secret ? Totp::uri($secret, $user->email, config('app.name')) : null;

        return view('account.two-factor', [
            'user' => $user, 'secret' => $secret ? Totp::readable($secret) : null, 'qr' => $uri ? Totp::qrSvg($uri) : null,
            'codes' => $request->session()->get('tweestaps.codes'), 'remaining' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $request->user();
        $secret = $request->session()->get('tweestaps.geheim');
        abort_if($user->hasTwoFactor() || ! $secret, 422, 'Tweestapsverificatie is al ingesteld of de sessie is verlopen.');
        $step = Totp::verify($secret, $request->input('code'));
        if (! $step) {
            throw ValidationException::withMessages(['code' => 'Deze code klopt niet. Controleer of de tijd op je telefoon goed staat en probeer de nieuwste code.']);
        }
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->saveQuietly();
        $codes = $user->newRecoveryCodes();
        $request->session()->forget('tweestaps.geheim');
        Audit::log('tweestaps_aangezet', $user, $user->email);

        return redirect()->route('account.tweestaps')->with('tweestaps.codes', $codes)->with('status', 'Tweestapsverificatie staat aan. Bewaar de herstelcodes op een veilige plek.');
    }

    public function codes(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 422);
        $codes = $user->newRecoveryCodes();
        Audit::log('tweestaps_herstelcodes_vernieuwd', $user, $user->email);

        return redirect()->route('account.tweestaps')->with('tweestaps.codes', $codes)->with('status', 'Nieuwe herstelcodes gemaakt; de oude werken niet meer.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['wachtwoord_uit' => ['required', 'current_password']], [], ['wachtwoord_uit' => 'huidig wachtwoord']);
        $user = $request->user();
        $user->disableTwoFactor();
        Audit::log('tweestaps_uitgezet', $user, $user->email);

        return redirect()->route('account.tweestaps')->with('status', 'Tweestapsverificatie staat uit.');
    }
}
