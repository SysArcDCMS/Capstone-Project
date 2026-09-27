<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Engineers may see only their own department's staff and complaints, and no
 * customer accounts at all (capstone role matrix).
 *
 * The scoping lives in App\Services\VisibilityScope; these tests pin the
 * behaviour at the API boundary so the list and the by-id routes cannot drift
 * apart or be bypassed.
 */
class EngineerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function incidentFor(User $customer, ?string $category): Incident
    {
        return Incident::factory()
            ->forCustomer($customer)
            ->categorisedAs($category ?? 'Metering')
            ->create();
    }

    // ── Users: no customer accounts, own department only ──────────────

    public function test_engineer_user_list_excludes_customers(): void
    {
        $customer = User::factory()->customer()->create();
        $engineer = User::factory()->engineer('metering')->create();

        $response = $this->actingAs($engineer, 'api')->getJson('/api/users');

        $response->assertOk();
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertFalse($emails->contains($customer->email));
        $this->assertNotContains(
            User::ROLE_CUSTOMER,
            collect($response->json('data'))->pluck('role')
        );
    }

    public function test_engineer_user_list_is_limited_to_own_department(): void
    {
        $engineer     = User::factory()->engineer('metering')->create();
        $mate         = User::factory()->engineer('metering')->create();
        $billingStaff = User::factory()->offsiteStaff('billing')->create();

        $response = $this->actingAs($engineer, 'api')->getJson('/api/users');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($engineer->id));
        $this->assertTrue($ids->contains($mate->id));
        $this->assertFalse($ids->contains($billingStaff->id));
    }

    public function test_engineer_with_no_department_sees_no_users(): void
    {
        $engineer = User::factory()->engineer(null)->create();
        User::factory()->engineer('metering')->create();
        User::factory()->customer()->create();

        $response = $this->actingAs($engineer, 'api')->getJson('/api/users');

        $response->assertOk();
        // A bare where('department_team', null) would become `IS NULL` and
        // match every customer row in the table.
        $this->assertCount(0, $response->json('data'));
    }

    public function test_administrator_still_sees_every_user(): void
    {
        User::factory()->customer()->create();
        User::factory()->engineer('billing')->create();
        $admin = User::factory()->administrator()->create();

        $response = $this->actingAs($admin, 'api')->getJson('/api/users');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    /**
     * The by-id route previously compared department_team only, so an engineer
     * could read any customer whose department was NULL.
     */
    public function test_engineer_cannot_fetch_a_customer_by_id(): void
    {
        $customer = User::factory()->customer()->create(['department_team' => null]);
        $engineer = User::factory()->engineer('metering')->create();

        $this->actingAs($engineer, 'api')
            ->getJson("/api/users/{$customer->id}")
            ->assertNotFound();
    }

    public function test_engineer_cannot_fetch_staff_from_another_department(): void
    {
        $other    = User::factory()->offsiteStaff('billing')->create();
        $engineer = User::factory()->engineer('metering')->create();

        $this->actingAs($engineer, 'api')
            ->getJson("/api/users/{$other->id}")
            ->assertNotFound();
    }

    public function test_engineer_can_fetch_own_department_mate(): void
    {
        $mate     = User::factory()->engineer('metering')->create();
        $engineer = User::factory()->engineer('metering')->create();

        $this->actingAs($engineer, 'api')
            ->getJson("/api/users/{$mate->id}")
            ->assertOk();
    }

    // ── Complaints: department categories plus personal assignments ──

    public function test_engineer_sees_own_department_complaints(): void
    {
        $customer = User::factory()->customer()->create();
        $mine     = $this->incidentFor($customer, 'Metering');
        $theirs   = $this->incidentFor($customer, 'Billing');
        $engineer = User::factory()->engineer('metering')->create();

        $ids = collect(
            $this->actingAs($engineer, 'api')->getJson('/api/incidents')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_engineer_sees_own_personal_assignment_in_another_department(): void
    {
        $customer = User::factory()->customer()->create();
        $incident  = $this->incidentFor($customer, 'Billing');
        $engineer  = User::factory()->engineer('metering')->create();

        Assignment::create([
            'incident_id'     => $incident->id,
            'team_leader_id'  => $engineer->id,
            'action_status'   => 'assigned',
        ]);

        $ids = collect(
            $this->actingAs($engineer, 'api')->getJson('/api/incidents')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($incident->id));
    }

    public function test_engineer_with_no_department_sees_only_own_assignments(): void
    {
        $customer = User::factory()->customer()->create();
        $assigned  = $this->incidentFor($customer, 'Metering');
        $unclaimed = $this->incidentFor($customer, 'Metering');
        $engineer  = User::factory()->engineer(null)->create();

        Assignment::create([
            'incident_id'     => $assigned->id,
            'team_leader_id'  => $engineer->id,
            'action_status'   => 'assigned',
        ]);

        $ids = collect(
            $this->actingAs($engineer, 'api')->getJson('/api/incidents')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($assigned->id));
        $this->assertFalse($ids->contains($unclaimed->id));
    }

    public function test_engineer_cannot_open_another_departments_complaint_by_id(): void
    {
        $customer = User::factory()->customer()->create();
        $incident = $this->incidentFor($customer, 'Billing');
        $engineer = User::factory()->engineer('metering')->create();

        $this->actingAs($engineer, 'api')
            ->getJson("/api/incidents/{$incident->id}")
            ->assertNotFound();
    }

    public function test_customer_only_sees_own_complaints(): void
    {
        $customer = User::factory()->customer()->create();
        $stranger = User::factory()->customer()->create();
        $mine     = $this->incidentFor($customer, 'Metering');
        $theirs   = $this->incidentFor($stranger, 'Metering');

        $ids = collect(
            $this->actingAs($customer, 'api')->getJson('/api/incidents')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));

        $this->actingAs($customer, 'api')
            ->getJson("/api/incidents/{$theirs->id}")
            ->assertNotFound();
    }

    public function test_administrator_sees_every_complaint(): void
    {
        $customer = User::factory()->customer()->create();
        $a = $this->incidentFor($customer, 'Metering');
        $b = $this->incidentFor($customer, 'Billing');
        $admin = User::factory()->administrator()->create();

        $ids = collect(
            $this->actingAs($admin, 'api')->getJson('/api/incidents')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));
    }

    // ── Assignments inherit the parent complaint's scope ──────────────

    public function test_engineer_assignment_list_is_department_limited(): void
    {
        $customer  = User::factory()->customer()->create();
        $mine      = $this->incidentFor($customer, 'Metering');
        $theirs    = $this->incidentFor($customer, 'Billing');
        $engineer  = User::factory()->engineer('metering')->create();
        $otherLead = User::factory()->offsiteStaff('billing')->create();

        // Both are led by someone other than the engineer, so the only thing
        // that can make the Billing one visible is department membership.
        $a = Assignment::create([
            'incident_id' => $mine->id, 'team_leader_id' => $otherLead->id, 'action_status' => 'assigned',
        ]);
        $b = Assignment::create([
            'incident_id' => $theirs->id, 'team_leader_id' => $otherLead->id, 'action_status' => 'assigned',
        ]);

        $ids = collect(
            $this->actingAs($engineer, 'api')->getJson('/api/assignments')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($a->id));
        $this->assertFalse($ids->contains($b->id));

        $this->actingAs($engineer, 'api')
            ->getJson("/api/assignments/{$b->id}")
            ->assertNotFound();
    }

    /**
     * A personal assignment overrides the department filter — the union is
     * "led by me" OR "my department's category", not department only.
     */
    public function test_engineer_sees_own_assignment_outside_their_department(): void
    {
        $customer = User::factory()->customer()->create();
        $incident = $this->incidentFor($customer, 'Billing');
        $engineer = User::factory()->engineer('metering')->create();

        $assignment = Assignment::create([
            'incident_id' => $incident->id, 'team_leader_id' => $engineer->id, 'action_status' => 'assigned',
        ]);

        $ids = collect(
            $this->actingAs($engineer, 'api')->getJson('/api/assignments')->json('data')
        )->pluck('id');

        $this->assertTrue($ids->contains($assignment->id));
    }

    // ── The scope must not be self-writable ───────────────────────────

    /**
     * An engineer must not be able to widen their own window by PATCHing
     * their own department_team, otherwise VisibilityScope is advisory only.
     */
    public function test_engineer_cannot_move_themselves_to_another_department(): void
    {
        $engineer = User::factory()->engineer('metering')->create();

        $this->actingAs($engineer, 'api')
            ->patchJson("/api/users/{$engineer->id}", ['department_team' => 'billing'])
            ->assertStatus(422);

        $this->assertSame('metering', $engineer->fresh()->department_team);
    }

    public function test_user_cannot_promote_themselves_to_team_leader(): void
    {
        $customer = User::factory()->customer()->create();
        $this->assertFalse((bool) $customer->is_team_leader);

        $this->actingAs($customer, 'api')
            ->patchJson("/api/users/{$customer->id}", ['is_team_leader' => true])
            ->assertStatus(422);

        $this->assertFalse((bool) $customer->fresh()->is_team_leader);
    }

    public function test_user_cannot_change_own_role_or_active_flag(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'api')
            ->patchJson("/api/users/{$customer->id}", [
                'role'      => User::ROLE_ADMINISTRATOR,
                'is_active' => true,
            ])
            ->assertStatus(422);

        $fresh = $customer->fresh();
        $this->assertSame(User::ROLE_CUSTOMER, $fresh->role);
    }

    public function test_user_can_still_update_their_own_contact_details(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'api')
            ->patchJson("/api/users/{$customer->id}", [
                'full_name'  => 'Renamed Person',
                'contact_no' => '08001234567',
            ])
            ->assertOk();

        $fresh = $customer->fresh();
        $this->assertSame('Renamed Person', $fresh->full_name);
        $this->assertSame('08001234567', $fresh->contact_no);
    }

    public function test_administrator_can_still_set_department_and_leader_flag(): void
    {
        $admin = User::factory()->administrator()->create();
        $staff = User::factory()->offsiteStaff('metering')->create();

        $this->actingAs($admin, 'api')
            ->patchJson("/api/users/{$staff->id}", [
                'department_team' => 'billing',
                'is_team_leader'  => true,
            ])
            ->assertOk();

        $fresh = $staff->fresh();
        $this->assertSame('billing', $fresh->department_team);
        $this->assertTrue((bool) $fresh->is_team_leader);
    }

    // ── Attachments follow the same scope ─────────────────────────────

    public function test_engineer_cannot_list_attachments_of_another_departments_incident(): void
    {
        $customer = User::factory()->customer()->create();
        $theirs   = $this->incidentFor($customer, 'Billing');
        $engineer = User::factory()->engineer('metering')->create();

        $this->actingAs($engineer, 'api')
            ->getJson("/api/incidents/{$theirs->id}/attachments")
            ->assertNotFound();
    }

    public function test_engineer_can_list_attachments_of_their_own_departments_incident(): void
    {
        $customer = User::factory()->customer()->create();
        $mine     = $this->incidentFor($customer, 'Metering');
        $engineer = User::factory()->engineer('metering')->create();

        $this->actingAs($engineer, 'api')
            ->getJson("/api/incidents/{$mine->id}/attachments")
            ->assertOk();
    }

    public function test_engineer_cannot_reroute_another_departments_incident(): void
    {
        $customer = User::factory()->customer()->create();
        $theirs   = $this->incidentFor($customer, 'Billing');
        $engineer = User::factory()->engineer('metering')->create();

        $this->actingAs($engineer, 'api')
            ->postJson("/api/incidents/{$theirs->id}/route")
            ->assertNotFound();
    }
}
