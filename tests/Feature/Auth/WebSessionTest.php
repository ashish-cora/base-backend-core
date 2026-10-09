<?php

namespace Tests\Feature\Auth;

use App\Core\Authentication\Notifications\ResetPasswordNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Tests\TestCase;

class WebSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deterministic Fortify TOTP provider so the web 2FA logic is exercised
     * without touching the underlying OTP engine or any Blade views.
     */
    protected function useFakeTwoFactor(): void
    {
        $this->app->singleton(TwoFactorAuthenticationProvider::class, fn () => new FakeWebTwoFactorProvider);
    }

    protected function confirmPasswordFor(User $user, string $password = 'secret123'): void
    {
        $this->actingAs($user)->post('/user/confirm-password', [
            'password' => $password,
        ])->assertRedirect();
    }

    public function test_register_route_is_disabled(): void
    {
        $this->post('/register', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertNotFound();
    }

    public function test_web_login_logout_and_remember_me(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->post('/login', [
            'email' => $user->email, 'password' => 'secret123', 'remember' => true,
        ])->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_web_login_blocked_for_suspended_account(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended', 'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-secret')]);

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'old-secret',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertRedirect();

        $this->post('/login', ['email' => $user->email, 'password' => 'brand-new-secret']);
        $this->assertAuthenticated();
    }

    public function test_home_redirects_guests_to_login_and_renders_logout_for_users(): void
    {
        $this->get('/home')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/home')->assertOk()->assertSee('Log out', false);
    }

    public function test_profile_information_update_over_web(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/user/profile-information', [
            'name' => 'New Name',
            'email' => $user->email,
        ])->assertRedirect();

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_confirm_password_cycle_over_web(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->actingAs($user)->post('/user/confirm-password', [
            'password' => 'wrong',
        ])->assertSessionHasErrors();

        $this->confirmPasswordFor($user);

        $this->actingAs($user)->get('/user/confirmed-password-status')->assertOk();
    }

    public function test_full_web_2fa_lifecycle_over_session(): void
    {
        $this->useFakeTwoFactor();

        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $this->confirmPasswordFor($user);

        // Enable → secret stored, recovery codes generated.
        $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_secret);

        // Confirm with the known code.
        $this->actingAs($user)->post('/user/confirmed-two-factor-authentication', [
            'code' => FakeWebTwoFactorProvider::CODE,
        ])->assertRedirect();
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        // QR payload + recovery codes endpoints work without views.
        $this->actingAs($user)->get('/user/two-factor-qr-code')->assertOk();
        $this->actingAs($user)->get('/user/two-factor-recovery-codes')->assertOk();

        // Login now challenges instead of authenticating.
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        // Correct challenge code completes authentication.
        $this->post('/two-factor-challenge', ['code' => FakeWebTwoFactorProvider::CODE])
            ->assertRedirect('/home');
        $this->assertAuthenticatedAs($user);

        // Disable with password confirmation.
        $this->confirmPasswordFor($user);
        $this->actingAs($user)->delete('/user/two-factor-authentication')->assertRedirect();
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_web_challenge_rejects_bad_code_without_exception(): void
    {
        $this->useFakeTwoFactor();

        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $this->confirmPasswordFor($user);
        $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect();
        $this->actingAs($user)->post('/user/confirmed-two-factor-authentication', [
            'code' => FakeWebTwoFactorProvider::CODE,
        ])->assertRedirect();

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertRedirect(route('two-factor.login'));

        // Bad code redirects back with errors.
        $this->post('/two-factor-challenge', ['code' => '000000'])
            ->assertRedirect(route('two-factor.login'))
            ->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_forgot_and_reset_password_cycle_over_web(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $this->resetToken = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $this->resetToken,
            'email' => $user->email,
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertRedirect(route('login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'new-secret-123']);
        $this->assertAuthenticated();
    }

    public function test_email_verification_over_web(): void
    {
        $user = User::factory()->unverified()->create();
        $this->assertFalse($user->hasVerifiedEmail());

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->getKey(), 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_password_page_requires_login_and_renders_form(): void
    {
        $this->get(route('profile.password'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('profile.password'))
            ->assertOk()
            ->assertSee('current_password', false)
            ->assertSee(route('user-password.update'), false);
    }

    public function test_password_page_shows_validation_errors(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-secret')]);

        $this->actingAs($user)->put('/user/password', [
            'current_password' => 'wrong',
            'password' => 'brand-new-secret',
            'password_confirmation' => 'brand-new-secret',
        ])->assertSessionHasErrors('current_password', null, 'updatePassword');

        $this->actingAs($user)->get(route('profile.password'))->assertOk();
    }

    public function test_two_factor_page_requires_login_and_toggles(): void
    {
        $this->get(route('profile.two-factor'))->assertRedirect(route('login'));

        $this->useFakeTwoFactor();
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        // State A (disabled): Enable only, never Disable/Cancel.
        // NOTE: enable (POST) and disable (DELETE) share the same URL, so
        // assert on button labels — not the URL — for exclusivity.
        $this->actingAs($user)->get(route('profile.two-factor'))
            ->assertOk()
            ->assertSee('Enable two-factor', false)
            ->assertSee(route('two-factor.enable'), false)
            ->assertDontSee('Disable two-factor', false)
            ->assertDontSee('Cancel setup', false);

        // Without recent password confirmation, Fortify redirects to confirm page.
        $this->actingAs($user->fresh())->post('/user/two-factor-authentication')
            ->assertRedirect(route('password.confirm'));

        // State B (confirming): QR + Confirm + Cancel, never Enable.
        $this->confirmPasswordFor($user);
        $this->actingAs($user)->post('/user/two-factor-authentication')->assertRedirect();
        $this->actingAs($user->refresh())->get(route('profile.two-factor'))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee(route('two-factor.confirm'), false)
            ->assertSee('Cancel setup', false)
            ->assertDontSee('Enable two-factor', false);

        // State C (enabled): Disable only, never Enable/Confirm.
        $this->actingAs($user->refresh())->post('/user/confirmed-two-factor-authentication', [
            'code' => FakeWebTwoFactorProvider::CODE,
        ])->assertRedirect();
        $this->actingAs($user->refresh())->get(route('profile.two-factor'))
            ->assertOk()
            ->assertSee('Two-factor is enabled.', false)
            ->assertSee('Disable two-factor', false)
            ->assertDontSee('Enable two-factor', false)
            ->assertDontSee(route('two-factor.confirm'), false);
    }

    public function test_reset_views_are_public_but_profile_pages_are_not(): void
    {
        // Public: forgot + reset views render without login.
        $this->get(route('password.request'))->assertOk();
        $this->get(route('password.reset', ['token' => 'dummy-token']))
            ->assertOk()
            ->assertSee('token', false)
            ->assertSee('email', false);

        // Auth-gated: guests never get 200 on profile pages.
        $this->get(route('profile.password'))->assertRedirect(route('login'));
        $this->get(route('profile.two-factor'))->assertRedirect(route('login'));
    }

    public function test_home_links_to_new_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertSee(route('profile.password'), false)
            ->assertSee(route('profile.two-factor'), false);
    }

    protected ?string $resetToken = null;
}

/**
 * Deterministic stand-in for Fortify's TOTP provider.
 * Accepts a single known code, so web 2FA logic tests are time-independent.
 */
class FakeWebTwoFactorProvider implements TwoFactorAuthenticationProvider
{
    public const SECRET = 'JBSWY3DPEHPK3PXP';

    public const CODE = '123456';

    public function generateSecretKey(int $secretLength = 16)
    {
        return self::SECRET;
    }

    public function qrCodeUrl($companyName, $companyEmail, $secret)
    {
        return 'otpauth://totp/'.$companyName.':'.$companyEmail.'?secret='.$secret.'&issuer='.$companyName;
    }

    public function verify($secret, $code)
    {
        return hash_equals(self::CODE, (string) $code);
    }
}
