<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Notifications\WachtwoordHerstellen;
use App\Support\Rechten;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'active', 'must_change_password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
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
