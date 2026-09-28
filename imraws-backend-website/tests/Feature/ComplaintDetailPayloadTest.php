<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The complaint detail screen is driven entirely by GET /api/incidents/{id},
 * so the by-id payload has to carry everything the mobile view renders:
 * the complainant's contact number, the assigned offsite person, the photo
 * proof, and the resolution note.
 *
 * These tests pin the payload shape and, just as importantly, pin that the
 * wider payload does not leak a complaint outside the actor's visibility.
 */
class ComplaintDetailPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function assignedComplaint(): array
    {
        Storage::fake('public');

        $customer = User::factory()->customer()->create([
            'full_name'  => 'Robert Dela Cruz',
            'contact_no' => '09171234567',
        ]);
        $staff = User::factory()->offsiteStaff('metering')->create([
            'full_name' => 'Team Leader Santos',
        ]);

        $incident = Incident::factory()
            ->forCustomer($customer)
            ->categorisedAs('Metering')
            ->create(['location' => 'Zone 4, Kamuning']);

        $assignment = Assignment::create([
            'incident_id'      => $incident->id,
            'team_leader_id'   => $staff->id,
            'action_status'    => 'resolved',
            'resolution_notes' => 'Meter resealed and reading verified.',
        ]);

        IncidentAttachment::create([
            'incident_id'   => $incident->id,
            'uploaded_by'   => $staff->id,
            'file_path'     => "attachments/{$incident->id}/proof.jpg",
            'original_name' => 'proof.jpg',
            'mime_type'     => 'image/jpeg',
            'file_size'     => 2048,
        ]);

        return [$customer, $staff, $incident, $assignment];
    }

    public function test_customer_detail_carries_every_field_the_mobile_view_renders(): void
    {
        [$customer, , $incident, $assignment] = $this->assignedComplaint();

        $response = $this->actingAs($customer, 'api')
            ->getJson("/api/incidents/{$incident->id}");

        $response->assertOk();

        $this->assertSame('Robert Dela Cruz', $response->json('data.customer.full_name'));
        $this->assertSame('09171234567', $response->json('data.customer.contact_no'));
        $this->assertSame('Team Leader Santos', $response->json('data.assignments.0.team_leader.full_name'));
        $this->assertSame(
            'Meter resealed and reading verified.',
            $response->json('data.assignments.0.resolution_notes')
        );
        $this->assertSame($assignment->id, $response->json('data.assignments.0.id'));

        $attachment = $response->json('data.attachments.0');
        $this->assertNotNull($attachment['url']);
        $this->assertStringContainsString("attachments/{$incident->id}/proof.jpg", $attachment['url']);
    }

    public function test_assigned_offsite_staff_sees_the_complainant_contact_number(): void
    {
        [, $staff, $incident] = $this->assignedComplaint();

        $response = $this->actingAs($staff, 'api')
            ->getJson("/api/incidents/{$incident->id}");

        $response->assertOk();
        $this->assertSame('Robert Dela Cruz', $response->json('data.customer.full_name'));
        $this->assertSame('09171234567', $response->json('data.customer.contact_no'));
        $this->assertSame('Team Leader Santos', $response->json('data.assignments.0.team_leader.full_name'));
    }

    public function test_complaint_list_also_carries_the_contact_number(): void
    {
        [$customer, , $incident] = $this->assignedComplaint();

        $listed = collect(
            $this->actingAs($customer, 'api')->getJson('/api/incidents')->json('data')
        )->firstWhere('id', $incident->id);

        $this->assertSame('09171234567', $listed['customer']['contact_no']);
        $this->assertSame(
            'Team Leader Santos',
            $listed['assignments'][0]['team_leader']['full_name']
        );
    }

    public function test_another_customer_cannot_read_the_complaint_detail(): void
    {
        [, , $incident] = $this->assignedComplaint();
        $stranger = User::factory()->customer()->create();

        $this->actingAs($stranger, 'api')
            ->getJson("/api/incidents/{$incident->id}")
            ->assertNotFound();
    }

    public function test_unassigned_offsite_staff_cannot_read_the_complaint_detail(): void
    {
        [, $staff, $incident] = $this->assignedComplaint();

        // Move the complaint to a different team leader; the original holder
        // must lose access along with the assignment.
        Assignment::where('id', $incident->assignments()->value('id'))
            ->update(['team_leader_id' => User::factory()->offsiteStaff('metering')->create()->id]);

        $this->actingAs($staff, 'api')
            ->getJson("/api/incidents/{$incident->id}")
            ->assertNotFound();
    }
}
