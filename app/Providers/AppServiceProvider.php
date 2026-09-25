<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions;
use App\Support\Settings;
use App\View\EmailBrand;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        View::composer('emails.*', EmailBrand::class);

        // Laravel's own password-reset and verification emails, in our design.
        ResetPassword::toMailUsing(function (User $user, string $token) {
            $minutes = (int) config('auth.passwords.users.expire');

            return (new MailMessage)
                ->subject('Reset your '.config('app.name').' portal password')
                ->view(['emails.password-reset', 'emails.password-reset-text'], [
                    'firstName' => Str::before($user->name, ' ') ?: $user->name,
                    'email' => $user->email,
                    'url' => url(route('password.reset', ['token' => $token, 'email' => $user->email], false)),
                    'expiresInMinutes' => $minutes,
                ]);
        });

        VerifyEmail::toMailUsing(fn (User $user, string $url) => (new MailMessage)
            ->subject('Confirm your email address')
            ->view(['emails.verify-email', 'emails.verify-email-text'], [
                'firstName' => Str::before($user->name, ' ') ?: $user->name,
                'email' => $user->email,
                'url' => $url,
            ]));

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
