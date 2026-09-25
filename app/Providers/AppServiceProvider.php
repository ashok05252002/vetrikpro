<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Registry permissions answer through the user's resolved set, so
         * `$user->can('projects.edit')`, `can:` route middleware and policies
         * all share one path. Anything else (policy abilities like `update`)
         * falls through to its policy.
         */
        Gate::before(function (User $user, string $ability) {
            return Permissions::exists($ability) ? $user->hasPermission($ability) : null;
        });

        /*
         * The company name drives config('app.name'), so the browser title, the
         * default mail "from" name and anything else reading it all follow the
         * setting rather than the .env value.
         *
         * Guarded on the table existing: boot runs before `migrate` on a fresh
         * database, and this must not break the install.
         */
        if (Settings::tableExists()) {
            $name = (string) $this->app->make(Settings::class)->get('company.name');

            if ($name !== '') {
                config(['app.name' => $name]);
            }
        }
    }
}
