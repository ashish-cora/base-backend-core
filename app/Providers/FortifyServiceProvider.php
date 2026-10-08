<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Laravel\Fortify\Fortify;
class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */             
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registration excluded: no CreateNewUser binding on purpose.
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // Starter-kit stubs: children replace resources/views/auth/* with their own screens.
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn ($request) => view('auth.reset-password', ['token' => $request->route('token')]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Credential validation per PRD §15.1: normalized email, account state,
        // no credential disclosure. Soft-deleted users are excluded by global scope.
        Fortify::authenticateUsing(function (Request $request) {
            $email = mb_strtolower(trim((string) $request->input(Fortify::username())));
            $user = User::where('email', $email)->first();

            if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
                return null;
            }

            if (! $user->isActive()) {
                Log::warning('auth.login.blocked_status', [
                    'request_id' => $request->header('X-Request-ID'),
                    'user_id' => $user->getKey(),
                    'status' => $user->status instanceof \BackedEnum ? $user->status->value : $user->status,
                    'ip' => $request->ip(),
                ]);

                return null;
            }

            return $user;
        });

        $this->registerSecurityEventLogging();

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            // Web-only core (Fortify session): throttle by the pending login
            // session. Token APIs live in the dedicated API repository.
            $key = $request->hasSession()
                ? $request->session()->get('login.id')
                : $request->ip();

            return Limit::perMinute(5)->by($key);
        });
    }

    /**
     * Security-event observability per PRD §15.7 (distinct from app/audit logs).
     * Never logs secrets, passwords, tokens or 2FA codes.
     */
    protected function registerSecurityEventLogging(): void
    {
        Event::listen(Login::class, function (Login $event) {
            Log::info('security.login.succeeded', [
                'user_id' => $event->user->getAuthIdentifier(),
                'guard' => $event->guard,
                'ip' => request()->ip(),
            ]);
        });

        Event::listen(Failed::class, function (Failed $event) {
            Log::warning('security.login.failed', [
                'guard' => $event->guard,
                'ip' => request()->ip(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            Log::info('security.logout', [
                'user_id' => $event->user?->getAuthIdentifier(),
                'guard' => $event->guard,
            ]);
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            Log::info('security.password.reset', [
                'user_id' => $event->user->getAuthIdentifier(),
            ]);
        });

        Event::listen(Verified::class, function (Verified $event) {
            Log::info('security.email.verified', [
                'user_id' => $event->user->getAuthIdentifier(),
            ]);
        });

        Event::listen(TwoFactorAuthenticationEnabled::class, fn ($e) => Log::info('security.2fa.enabled', ['user_id' => $e->user->getAuthIdentifier()]));
        Event::listen(TwoFactorAuthenticationConfirmed::class, fn ($e) => Log::info('security.2fa.confirmed', ['user_id' => $e->user->getAuthIdentifier()]));
        Event::listen(TwoFactorAuthenticationDisabled::class, fn ($e) => Log::info('security.2fa.disabled', ['user_id' => $e->user->getAuthIdentifier()]));
        Event::listen(ValidTwoFactorAuthenticationCodeProvided::class, fn ($e) => Log::info('security.2fa.challenge.succeeded', ['user_id' => $e->user->getAuthIdentifier()]));
        Event::listen(TwoFactorAuthenticationFailed::class, fn ($e) => Log::warning('security.2fa.challenge.failed', ['user_id' => $e->user?->getAuthIdentifier()]));
        Event::listen(RecoveryCodeReplaced::class, fn ($e) => Log::info('security.2fa.recovery_used', ['user_id' => $e->user->getAuthIdentifier()]));
        Event::listen(PasswordUpdatedViaController::class, fn ($e) => Log::info('security.password.updated', ['user_id' => $e->user->getAuthIdentifier()]));
    }
}
