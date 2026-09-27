<?php

namespace Tests\Feature;

use App\Mail\ResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Password recovery by emailed 6-digit code (capstone DFD 1.9).
 *
 * Two properties matter more than the happy path:
 *   - the request/response must not reveal whether an address is registered;
 *   - the code must be unusable once spent, expired, or beaten 5 times.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'resident@example.com'): User
    {
        return User::factory()->customer()->create([
            'email'    => $email,
            'password' => Hash::make('old-password'),
        ]);
    }

    /** Pull the code out of the captured message. */
    private function capturedCode(): string
    {
        $code = null;

        Mail::assertSent(ResetCodeMail::class, function (ResetCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return (string) $code;
    }

    // ── Requesting a code ─────────────────────────────────────────────

    public function test_requesting_a_code_emails_a_six_digit_code(): void
    {
        Mail::fake();
        $user = $this->user();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(202);

        Mail::assertSent(ResetCodeMail::class, function (ResetCodeMail $mail) {
            return preg_match('/^\d{6}$/', $mail->code) === 1;
        });

        $this->assertDatabaseHas('password_reset_codes', ['email' => $user->email]);
    }

    public function test_the_code_is_stored_hashed_not_in_plaintext(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);

        $record = PasswordResetCode::where('email', $user->email)->firstOrFail();
        $code   = $this->capturedCode();

        $this->assertNotSame($code, $record->code_hash);
        $this->assertTrue(Hash::check($code, $record->code_hash));
    }

    /**
     * A different reply for an unknown address would let anyone enumerate
     * which people hold accounts.
     */
    public function test_unknown_email_is_indistinguishable_and_sends_nothing(): void
    {
        Mail::fake();
        $this->user('resident@example.com');

        $known   = $this->postJson('/api/auth/forgot-password', ['email' => 'resident@example.com']);
        $unknown = $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertStatus(202);
        $unknown->assertStatus(202);

        $this->assertSame($known->json(), $unknown->json());
        Mail::assertSentCount(1);
    }

    public function test_email_is_matched_case_insensitively(): void
    {
        Mail::fake();
        $user = $this->user('Resident@Example.com');

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'resident@example.com',
        ])->assertStatus(202);

        // The row is stored lowercased, whatever case the account was
        // registered with.
        $this->assertDatabaseHas('password_reset_codes', ['email' => 'resident@example.com']);

        $code = $this->capturedCode();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => 'RESIDENT@example.COM',
            'code'                  => $code,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_a_new_request_retires_the_previous_code(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $first = $this->capturedCode();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $second = $this->capturedCode();

        $this->assertCount(1, PasswordResetCode::where('email', $user->email)->get());

        // The superseded code must no longer work.
        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => $first,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
        $this->assertNotSame($first, $second);
    }

    // ── Using the code ────────────────────────────────────────────────

    public function test_a_valid_code_sets_the_new_password(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $code = $this->capturedCode();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_the_new_password_actually_signs_in(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $code = $this->capturedCode();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'brand-new-password',
        ])->assertOk();
    }

    public function test_a_code_cannot_be_reused(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $code = $this->capturedCode();

        $payload = [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ];

        $this->postJson('/api/auth/reset-password', $payload)->assertOk();
        $this->postJson('/api/auth/reset-password', $payload)->assertStatus(422);
    }

    public function test_a_wrong_code_is_rejected_and_changes_nothing(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $this->capturedCode();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => '000000',
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_an_expired_code_is_rejected(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $code = $this->capturedCode();

        PasswordResetCode::where('email', $user->email)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_the_code_is_destroyed_after_five_wrong_guesses(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $this->capturedCode();

        $attempt = fn (string $guess) => $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => $guess,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        // The fifth miss exhausts the budget, so the reply switches from
        // "wrong code" to "ask for a new one".
        for ($i = 0; $i < 4; $i++) {
            $attempt('00000' . $i)->assertStatus(422);
        }

        $attempt('999999')->assertStatus(429);

        $this->assertDatabaseMissing('password_reset_codes', ['email' => $user->email]);
        $this->assertTrue(
            Hash::check('old-password', $user->fresh()->password),
            'A burned code must not change the password.'
        );
    }

    public function test_lockout_is_recoverable_by_requesting_a_new_code(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $this->capturedCode();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/reset-password', [
                'email'                 => $user->email,
                'code'                  => '00000' . $i,
                'password'              => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $fresh = $this->capturedCode();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => $fresh,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $code = $this->capturedCode();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'brand-new-password',
            'password_confirmation' => 'something-else',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_short_passwords_are_rejected(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/auth/forgot-password', ['email' => $user->email]);
        $code = $this->capturedCode();

        $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'code'                  => $code,
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_resetting_an_unknown_address_reports_a_bad_code(): void
    {
        $this->postJson('/api/auth/reset-password', [
            'email'                 => 'nobody@example.com',
            'code'                  => '123456',
            'password'              => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertStatus(422);
    }

    // ── Abuse limits ──────────────────────────────────────────────────

    public function test_code_requests_are_throttled(): void
    {
        Mail::fake();

        foreach (range(1, 3) as $ignored) {
            $this->postJson('/api/auth/forgot-password', [
                'email' => 'resident@example.com',
            ])->assertStatus(202);
        }

        $this->postJson('/api/auth/forgot-password', [
            'email' => 'resident@example.com',
        ])->assertStatus(429);
    }
}
