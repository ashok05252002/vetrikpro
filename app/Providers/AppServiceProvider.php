<?php

namespace App\Providers;

use App\Support\Settings;
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
