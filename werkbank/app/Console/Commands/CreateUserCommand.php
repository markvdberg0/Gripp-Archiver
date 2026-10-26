<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Rechten;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateUserCommand extends Command
{
    protected $signature = 'werkbank:gebruiker
        {email : E-mailadres}
        {--naam= : Naam (standaard het deel vóór de @)}
        {--rol=beheerder : '.'beheerder, administratie, projectleider, medewerker of alleen_lezen}';

    protected $description = 'Maak een gebruiker aan (bijvoorbeeld de eerste beheerder) met een tijdelijk wachtwoord';

    public function handle(): int
    {
        $data = ['email' => $this->argument('email'), 'name' => $this->option('naam') ?: Str::before($this->argument('email'), '@'), 'role' => $this->option('rol')];
        $v = Validator::make($data, [
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(Rechten::ROLLEN))],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }
        $password = Str::password(16, symbols: false);
        User::create($data + ['password' => $password, 'must_change_password' => true, 'active' => true]);
        $this->info("Gebruiker {$data['email']} ({$data['role']}) aangemaakt.");
        $this->line("Tijdelijk wachtwoord: {$password}");
        $this->line('Bij de eerste keer inloggen moet een eigen wachtwoord worden gekozen.');

        return self::SUCCESS;
    }
}
