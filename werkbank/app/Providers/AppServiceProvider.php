<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Attachables;
use App\Support\Rechten;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Kolommen voor records die uit een Gripp-back-up kunnen komen (zie App\Import).
        Blueprint::macro('gripp', function () {
            /** @var Blueprint $this */
            $this->unsignedBigInteger('gripp_id')->nullable()->unique();
            $this->string('gripp_hash', 40)->nullable();
            $this->timestamp('gripp_synced_at')->nullable();
        });

        // Korte, stabiele namen in polymorfe kolommen (bijlagen, labels, logboek).
        Relation::morphMap(Attachables::morphMap());

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        foreach (array_keys(Rechten::RECHTEN) as $recht) {
            Gate::define($recht, fn (User $user) => $user->active && Rechten::heeft($user->role, $recht));
        }

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        Paginator::defaultView('partials.paginatie');
        Paginator::defaultSimpleView('partials.paginatie');
    }
}
