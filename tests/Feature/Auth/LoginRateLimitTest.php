<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $payload = ['email' => $user->email, 'password' => 'wrong-password'];

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/login', $payload);
            $this->assertNotEquals(
                429,
                $response->getStatusCode(),
                "Attempt {$i} should not be throttled."
            );
        }

        $this->post('/login', $payload)->assertStatus(429);
        $this->assertGuest();
    }

    public function test_login_throttle_is_scoped_to_email_and_ip(): void
    {
        $userA = User::factory()->create(['password' => Hash::make('secret123')]);
        $userB = User::factory()->create(['password' => Hash::make('secret123')]);

        $payloadA = ['email' => $userA->email, 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', $payloadA);
        }
        $this->post('/login', $payloadA)->assertStatus(429);

        // Same IP but different email gets its own bucket (email|ip key).
        $response = $this->post('/login', ['email' => $userB->email, 'password' => 'wrong-password']);
        $this->assertNotEquals(429, $response->getStatusCode());
    }
}
