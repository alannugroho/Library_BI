<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_log_in(): void
    {
        $user = User::factory()->anggota()->create();

        $response = $this->postLogin($user->email, 'password');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_pending_user_cannot_log_in(): void
    {
        $user = User::factory()->anggota()->pending()->create();

        $response = $this->postLogin($user->email, 'password');

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->anggota()->inactive()->create();

        $response = $this->postLogin($user->email, 'password');

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->anggota()->create();

        $response = $this->postLogin($user->email, 'wrong-password');

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->anggota()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postLogin($user->email, 'wrong-password');
        }

        $response = $this->postLogin($user->email, 'password');

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function postLogin(string $email, string $password): TestResponse
    {
        return $this->withSession(['_token' => 'test-token'])->post('/login', [
            'email' => $email,
            'password' => $password,
            '_token' => 'test-token',
        ]);
    }
}
