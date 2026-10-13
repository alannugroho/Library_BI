<?php

namespace Tests\Feature;

use App\Mail\VerifyEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ExternalRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_registration_creates_pending_account_and_sends_verification_email(): void
    {
        Mail::fake();

        $this->post('/register', [
            'email' => 'external@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/login');

        $user = User::where('email', 'external@example.test')->firstOrFail();
        $this->assertSame('eksternal', $user->role);
        $this->assertSame('pending', $user->status);
        $this->assertNotNull(DB::table('email_verifications')->where('user_id', $user->id)->first());

        Mail::assertSent(VerifyEmail::class, function (VerifyEmail $mail): bool {
            return $mail->hasTo('external@example.test')
                && str_contains($mail->verificationUrl, '/verify-email?token=');
        });
    }

    public function test_external_can_verify_email_then_log_in_without_librarian_approval(): void
    {
        $this->post('/register', [
            'email' => 'external@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/login');

        $user = User::where('email', 'external@example.test')->firstOrFail();

        $this->postLogin('external@example.test', 'password')->assertSessionHasErrors('email');
        $this->assertGuest();

        $plainToken = 'a'.str_repeat('b', 63);
        DB::table('email_verifications')->where('user_id', $user->id)->update([
            'token' => hash('sha256', $plainToken),
        ]);

        $this->get('/verify-email?token='.$plainToken)->assertOk();
        $this->assertSame('active', $user->fresh()->status);

        $this->postLogin('external@example.test', 'password')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_invalid_token_does_not_activate_account(): void
    {
        $user = User::factory()->eksternal()->create(['email' => 'external@example.test']);

        $this->get('/verify-email?token='.str_repeat('c', 64))->assertOk();

        $this->assertSame('pending', $user->fresh()->status);
    }

    public function test_expired_token_does_not_activate_account(): void
    {
        $user = User::factory()->eksternal()->create(['email' => 'external@example.test']);
        $plainToken = 'd'.str_repeat('e', 63);
        DB::table('email_verifications')->insert([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get('/verify-email?token='.$plainToken)->assertOk();

        $this->assertSame('pending', $user->fresh()->status);
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
