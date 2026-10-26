<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Console\Command;

/** Noodgeval: tweestapsverificatie uitzetten als niemand meer kan inloggen (bijvoorbeeld na herstel met een andere APP_KEY). */
class DisableTwoFactorCommand extends Command
{
    protected $signature = 'werkbank:tweestaps-uit {email? : E-mailadres; leeg met --alle} {--alle : Voor alle gebruikers}';

    protected $description = 'Zet tweestapsverificatie uit voor een gebruiker (of iedereen met --alle)';

    public function handle(): int
    {
        $query = User::query()->whereNotNull('two_factor_confirmed_at');
        if (! $this->option('alle')) {
            if (! $this->argument('email')) {
                $this->error('Geef een e-mailadres of gebruik --alle.');

                return self::FAILURE;
            }
            $query->where('email', $this->argument('email'));
        }
        $users = $query->get();
        foreach ($users as $user) {
            $user->disableTwoFactor();
            Audit::log('tweestaps_reset', $user, $user->email.' (via opdrachtregel)');
        }
        $this->info("Tweestapsverificatie uitgezet voor {$users->count()} ".($users->count() === 1 ? 'gebruiker' : 'gebruikers').'.');

        return self::SUCCESS;
    }
}
