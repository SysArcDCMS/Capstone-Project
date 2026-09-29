<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DFD 1.8 — Update Profile Information.
 *
 * These tests exist because of a silent data-loss bug. The login response
 * omitted `contact_no` and `address` while GET /api/auth/me included them, so
 * after signing in the app's cached user had both fields as null. The account
 * screen seeds its text boxes from that object, and its save posted every box
 * unconditionally — so correcting a name submitted the empty contact number
 * and address back and wiped the stored values. The user saw "Changes saved
 * successfully" and had simply lost their details.
 *
 * The shape assertions below are the real guard: if a future edit drops a
 * field from one endpoint, these fail rather than letting a client decide
 * that "missing" means "empty".
 */
class ProfileUpdateSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->customer()->create([
            'full_name'  => 'Robert Dela Cruz',
            'email'      => 'robert@example.test',
            'contact_no' => '09171234567',
            'address'    => '12 Example St, Malabon City',
        ]);
    }

    public function test_login_returns_every_user_field_that_me_returns(): void
    {
        $user = $this->customer();

        $login = $this->postJson('/api/auth/login', [
            'email'    => 'robert@example.test',
            'password' => 'password',
        ])->assertOk();

        $me = $this->actingAs($user->fresh(), 'api')->getJson('/api/auth/me')->assertOk();

        $loginFields = array_keys($login->json('user'));
        $meFields = array_keys($me->json('data'));

        // `me` adds a few read-only extras, but the login payload must not be
        // missing anything the app needs to fill its form.
        foreach ($meFields as $field) {
            $this->assertContains(
                $field,
                $loginFields,
                "login response is missing `{$field}`; the app would treat it as empty and post it back"
            );
        }
    }

    public function test_login_carries_the_contact_number_and_address(): void
    {
        $this->customer();

        $login = $this->postJson('/api/auth/login', [
            'email'    => 'robert@example.test',
            'password' => 'password',
        ])->assertOk();

        $this->assertSame('09171234567', $login->json('user.contact_no'));
        $this->assertSame('12 Example St, Malabon City', $login->json('user.address'));
    }

    public function test_correcting_only_the_name_leaves_the_other_fields_intact(): void
    {
        $user = $this->customer();

        // Exactly what the account screen sends when only the name box is
        // edited: contact_no and address are absent from the body entirely.
        $this->actingAs($user, 'api')
            ->patchJson('/api/auth/profile', ['full_name' => 'Robert Dela Cruz Jr.'])
            ->assertOk();

        $user->refresh();
        $this->assertSame('Robert Dela Cruz Jr.', $user->full_name);
        $this->assertSame('09171234567', $user->contact_no);
        $this->assertSame('12 Example St, Malabon City', $user->address);
    }

    public function test_clearing_a_field_stores_null_rather_than_an_empty_string(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'api')
            ->patchJson('/api/auth/profile', ['contact_no' => ''])
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->contact_no);
        $this->assertSame('12 Example St, Malabon City', $user->address);
    }

    public function test_updating_several_fields_at_once_applies_all_of_them(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'api')
            ->patchJson('/api/auth/profile', [
                'full_name'  => 'Robert DC',
                'contact_no' => '09991112222',
                'address'    => '99 New Road, Caloocan',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertSame('Robert DC', $user->full_name);
        $this->assertSame('09991112222', $user->contact_no);
        $this->assertSame('99 New Road, Caloocan', $user->address);
    }

    public function test_the_response_reports_the_stored_values(): void
    {
        $user = $this->customer();

        $response = $this->actingAs($user, 'api')
            ->patchJson('/api/auth/profile', ['full_name' => 'Robert DC'])
            ->assertOk();

        $this->assertSame('Robert DC', $response->json('data.full_name'));
        $this->assertSame('09171234567', $response->json('data.contact_no'));
        $this->assertSame('12 Example St, Malabon City', $response->json('data.address'));
    }

    public function test_the_email_is_ignored_rather_than_changed(): void
    {
        $user = $this->customer();

        // `email` is not a validated field, so it is dropped from the payload
        // instead of being applied. Changing the login address is a separate
        // flow with its own verification, so this must never be a shortcut.
        $response = $this->actingAs($user, 'api')
            ->patchJson('/api/auth/profile', ['email' => 'attacker@example.test'])
            ->assertOk();

        $this->assertSame('robert@example.test', $user->refresh()->email);
        $this->assertSame('09171234567', $response->json('data.contact_no'), 'sanity: nothing else was dropped');
    }

    public function test_the_endpoint_always_edits_the_caller_and_nobody_else(): void
    {
        $victim = $this->customer();
        $attacker = User::factory()->customer()->create([
            'full_name' => 'Mallory',
        ]);

        // There is no user id in the route, so the only reachable record is
        // the caller's own — a submitted "user_id" or "id" changes nothing.
        $this->actingAs($attacker, 'api')
            ->patchJson('/api/auth/profile', [
                'id'        => $victim->id,
                'user_id'   => $victim->id,
                'full_name' => 'Mallory Renamed',
            ])
            ->assertOk();

        $this->assertSame('Mallory Renamed', $attacker->refresh()->full_name);
        $this->assertSame('Robert Dela Cruz', $victim->refresh()->full_name);
    }
}
