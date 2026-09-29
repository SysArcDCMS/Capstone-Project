<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Category;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status is the offsite team's task, so nothing else may move a complaint
 * along (capstone role matrix).
 *
 * Two separate surfaces are pinned here, because both were writable before:
 *
 *   1. The web portal exposed PATCH /complaints/{id}/status and
 *      POST /complaints/{id}/resolve to administrators and engineers, with
 *      matching forms in the action modal. The routes now do not exist, so
 *      the tests assert 404 rather than 403 — a removed route must not leave a
 *      reachable action behind, and a 403 would still prove the route exists.
 *
 *   2. PATCH /api/incidents/{id}/status listed engineer and administrator in
 *      its role middleware. Those roles now get 403 and the complaint is left
 *      untouched.
 */
class StatusIsOffsiteOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function incident(): Incident
    {
        $customer = User::factory()->customer()->create();

        return Incident::factory()
            ->forCustomer($customer)
            ->categorisedAs('Metering')
            ->create(['status' => Incident::STATUS_ASSIGNED]);
    }

    // ── Web portal: the write routes are gone ─────────────────────────

    public static function webStatusWriteUrls(): array
    {
        return [
            'status'  => ['patch', '/complaints/{id}/status'],
            'resolve' => ['post', '/complaints/{id}/resolve'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('webStatusWriteUrls')]
    public function test_web_status_write_routes_are_removed(string $method, string $url): void
    {
        $actor = User::factory()->administrator()->create();
        $incident = $this->incident();

        $response = $this->actingAs($actor, 'web')->json($method, str_replace('{id}', (string) $incident->id, $url));

        $response->assertNotFound();
    }

    public function test_administrator_cannot_resolve_via_a_renamed_endpoint(): void
    {
        // The two removed routes are not the only way a status could be
        // written. The surviving complaint routes answer GET only, so a PATCH
        // against one must be refused rather than quietly handled — otherwise
        // the action could be smuggled back under a different path.
        $actor = User::factory()->administrator()->create();
        $incident = $this->incident();

        $response = $this->actingAs($actor, 'web')
            ->patch("/complaints/{$incident->id}/modal", ['status' => Incident::STATUS_RESOLVED]);

        $response->assertStatus(405);
        $this->assertSame(Incident::STATUS_ASSIGNED, $incident->fresh()->status);
    }

    public function test_removing_the_routes_left_the_incident_unchanged(): void
    {
        $actor = User::factory()->engineer('metering')->create();
        $incident = $this->incident();
        $before = $incident->only(['status', 'resolved_at']);

        $this->actingAs($actor, 'web')
            ->post("/complaints/{$incident->id}/resolve", ['resolution_notes' => 'done by the office'])
            ->assertNotFound();

        $this->assertSame($before, $incident->fresh()->only(['status', 'resolved_at']));
    }

    // ── Web portal: the surviving read endpoints still work ───────────

    public function test_engineer_can_still_read_the_complaint_and_its_modal(): void
    {
        $actor = User::factory()->engineer('metering')->create();
        $incident = $this->incident();

        $this->actingAs($actor, 'web')->get("/complaints/{$incident->id}")->assertOk();

        // The modal still loads, and it still reports the status — the portal
        // is view-only, not status-blind.
        $this->actingAs($actor, 'web')
            ->getJson("/complaints/{$incident->id}/modal")
            ->assertOk()
            ->assertJsonPath('data.status', Incident::STATUS_ASSIGNED);
    }

    public function test_category_correction_is_still_available_to_an_engineer(): void
    {
        // Only status moved offsite. Correcting a misfiled category is still
        // the office's job, so the modal must keep this one write. It needs a
        // real, active category row and someone to route to — the endpoint
        // answers 422 "No available staff" when the department is empty, which
        // is a different failure from the route being gone.
        Category::create([
            'category_name' => 'Water Quality',
            'label'         => 'Water Quality',
            'is_active'     => true,
        ]);

        // The router only offers a leader who is on duty, so the fixture needs
        // an availability row too. Without it the endpoint answers 422 "No
        // available staff", which would mask the thing being tested.
        $leader = User::factory()->offsiteStaff('water_quality')->create();
        Availability::create([
            'staff_id' => $leader->id,
            'status'   => Availability::STATUS_AVAILABLE,
        ]);

        $actor = User::factory()->engineer('metering')->create();
        $incident = $this->incident();

        $this->actingAs($actor, 'web')
            ->postJson("/complaints/{$incident->id}/route", ['category' => 'Water Quality'])
            ->assertOk();

        $this->assertSame('Water Quality', $incident->fresh()->category);
    }

    // ── API: offsite staff only ───────────────────────────────────────

    public function test_engineer_cannot_update_status_over_the_api(): void
    {
        $engineer = User::factory()->engineer('metering')->create();
        $incident = $this->incident();

        $response = $this->actingAs($engineer, 'api')
            ->patchJson("/api/incidents/{$incident->id}/status", [
                'status' => Incident::STATUS_IN_PROGRESS,
            ]);

        $response->assertForbidden();
        $this->assertSame(Incident::STATUS_ASSIGNED, $incident->fresh()->status);
    }

    public function test_administrator_cannot_update_status_over_the_api(): void
    {
        $admin = User::factory()->administrator()->create();
        $incident = $this->incident();

        $response = $this->actingAs($admin, 'api')
            ->patchJson("/api/incidents/{$incident->id}/status", [
                'status' => Incident::STATUS_RESOLVED,
            ]);

        $response->assertForbidden();
        $this->assertSame(Incident::STATUS_ASSIGNED, $incident->fresh()->status);
    }

    public function test_customer_cannot_update_status_over_the_api(): void
    {
        $customer = User::factory()->customer()->create();
        $incident = $this->incident();

        $response = $this->actingAs($customer, 'api')
            ->patchJson("/api/incidents/{$incident->id}/status", [
                'status' => Incident::STATUS_RESOLVED,
            ]);

        $response->assertForbidden();
        $this->assertSame(Incident::STATUS_ASSIGNED, $incident->fresh()->status);
    }

    public function test_engineer_cannot_resolve_even_with_resolution_notes(): void
    {
        // Resolving writes resolved_at and notifies the customer, so it must
        // be closed off just as firmly as a plain status change.
        $engineer = User::factory()->engineer('metering')->create();
        $incident = $this->incident();

        $this->actingAs($engineer, 'api')
            ->patchJson("/api/incidents/{$incident->id}/status", [
                'status'            => Incident::STATUS_RESOLVED,
                'resolution_notes'  => 'handled centrally',
            ])
            ->assertForbidden();

        $incident->refresh();
        $this->assertSame(Incident::STATUS_ASSIGNED, $incident->status);
        $this->assertNull($incident->resolved_at);
    }

    public function test_offsite_staff_can_still_update_status(): void
    {
        // Offsite staff reach a complaint through their assignment, not their
        // department, so the fixture has to give them one.
        $staff = User::factory()->offsiteStaff('metering')->create();
        $incident = $this->incident();
        Assignment::create([
            'incident_id'     => $incident->id,
            'team_leader_id'  => $staff->id,
            'assigned_at'     => now(),
            'action_status'   => Assignment::ACTION_PENDING,
        ]);

        $this->actingAs($staff, 'api')
            ->patchJson("/api/incidents/{$incident->id}/status", [
                'status' => Incident::STATUS_IN_PROGRESS,
            ])
            ->assertOk();

        $this->assertSame(Incident::STATUS_IN_PROGRESS, $incident->fresh()->status);
    }
}
