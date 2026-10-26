<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Notifications\WachtwoordHerstellen;
use App\Support\Rechten;
use App\Support\Totp;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'active', 'must_change_password'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array', // bcrypt-hashes, net als het wachtwoord
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactor(): bool
    {
        // Ruwe waarde: niet ontsleutelen (dat faalt na herstel met een andere APP_KEY).
        return $this->two_factor_confirmed_at !== null && $this->getAttributes()['two_factor_secret'] !== null;
    }

    /** Code uit de app of een herstelcode controleren; een herstelcode is daarna verbruikt. */
    public function verifyTwoFactor(string $input): ?string
    {
        if (! $this->hasTwoFactor()) {
            return null;
        }
        try {
            $secret = $this->two_factor_secret;
        } catch (DecryptException $e) {
            // Andere APP_KEY (herstel op een nieuwe server): herstelcodes werken nog wel (hashes, niet versleuteld);
            // anders zet `werkbank:tweestaps-uit` het voor deze gebruiker uit.
            report($e);
            $secret = null;
        }
        if ($secret && $step = Totp::verify($secret, $input, $this->two_factor_last_step)) {
            $this->forceFill(['two_factor_last_step' => $step])->saveQuietly();

            return 'app';
        }
        $normalized = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $input));
        $codes = $this->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $hash) {
            if ($normalized !== '' && Hash::check($normalized, $hash)) {
                unset($codes[$i]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->saveQuietly();

                return 'herstelcode';
            }
        }

        return null;
    }

    /** @return list<string> nieuwe herstelcodes (alleen nu leesbaar; bewaard als hash) */
    public function newRecoveryCodes(): array
    {
        $plain = array_map(fn () => strtolower(Str::random(5).'-'.Str::random(5)), range(1, 8));
        $this->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => Hash::make(str_replace('-', '', $c)), $plain)])->saveQuietly();

        return $plain;
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->saveQuietly();
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new WachtwoordHerstellen($token));
    }

    public function roleLabel(): string
    {
        return Rechten::ROLLEN[$this->role] ?? $this->role;
    }
}
