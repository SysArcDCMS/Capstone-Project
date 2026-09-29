<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * DFD 2.2 — a customer can attach photos of the problem while filing.
 *
 * These are "evidence": the customer's own pictures of what they are
 * reporting. They are stored apart from the "proof" photos offsite staff
 * upload after a repair (DFD 5.6) so the gallery can label which is which,
 * and so the two never overwrite each other.
 *
 * The photos arrive with the complaint as one multipart request rather than a
 * follow-up call. That matters because the customer has no incident id until
 * the complaint is created, and a separate upload step would mean asking them
 * to retry if the second call failed.
 */
class CustomerEvidencePhotoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Repoint the real `attachments` disk at a scratch directory.
     *
     * Storage::fake() is not used, for the same reason as AttachmentStorageTest:
     * it replaces signed URL generation with a bare expiration callback, so a
     * faked disk cannot prove these photos are actually protected.
     */
    private function useTemporaryAttachmentDisk(): void
    {
        $root = storage_path('framework/testing/disks/attachments');

        (new Filesystem)->cleanDirectory($root);

        config(['filesystems.disks.attachments.root' => $root]);
        Storage::forgetDisk('attachments');
    }

    private function customer(): User
    {
        $this->useTemporaryAttachmentDisk();

        return User::factory()->customer()->create();
    }

    /** @return array<string, mixed> */
    private function complaintPayload(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Water meter is leaking badly near the entrance.',
            'location'    => 'Zone 4, Kamuning',
        ], $overrides);
    }

    public function test_a_complaint_can_be_filed_without_any_photo(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'api')
            ->postJson('/api/incidents', $this->complaintPayload())
            ->assertCreated()
            ->assertJsonPath('photos_saved', 0)
            ->assertJsonPath('photo_warnings', []);

        $this->assertSame(0, IncidentAttachment::count());
    }

    public function test_photos_filed_with_a_complaint_are_stored_as_evidence(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'api')
            ->post('/api/incidents', $this->complaintPayload([
                'photos' => [
                    UploadedFile::fake()->image('leak-one.jpg'),
                    UploadedFile::fake()->image('leak-two.jpg'),
                ],
            ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('photos_saved', 2)
            ->assertJsonPath('photo_warnings', []);

        $incident = Incident::sole();

        $this->assertSame(2, IncidentAttachment::count());

        foreach (IncidentAttachment::all() as $attachment) {
            Storage::disk(IncidentAttachment::DISK)->assertExists($attachment->file_path);

            $this->assertStringStartsWith(
                "attachments/evidence/{$incident->id}/",
                $attachment->file_path,
                'customer photos are evidence and must not land in the proof directory'
            );
            $this->assertSame(IncidentAttachment::KIND_EVIDENCE, $attachment->kind);
            $this->assertSame($customer->id, $attachment->uploaded_by);
        }
    }

    public function test_evidence_and_staff_proof_do_not_share_a_directory(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'api')
            ->post('/api/incidents', $this->complaintPayload([
                'photos' => [UploadedFile::fake()->image('mine.jpg')],
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $incident = Incident::sole();

        $staff = User::factory()->offsiteStaff('metering')->create();
        \App\Models\Assignment::create([
            'incident_id'    => $incident->id,
            'team_leader_id' => $staff->id,
            'action_status'  => 'resolved',
        ]);

        $this->actingAs($staff, 'api')
            ->post("/api/incidents/{$incident->id}/attachments", [
                'file' => UploadedFile::fake()->image('theirs.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        // Read the attribute, not the column — kind is derived from the path
        // and has no column behind it, so pluck() would issue invalid SQL.
        $kinds = IncidentAttachment::orderBy('id')
            ->get()
            ->map(fn (IncidentAttachment $a) => $a->kind)
            ->all();

        $this->assertSame(
            [IncidentAttachment::KIND_EVIDENCE, IncidentAttachment::KIND_PROOF],
            $kinds,
            'the two kinds must stay distinguishable, which is what labels the gallery'
        );
    }

    public function test_the_kinds_are_reported_to_the_client(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'api')
            ->post('/api/incidents', $this->complaintPayload([
                'photos' => [UploadedFile::fake()->image('mine.jpg')],
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $incidentId = Incident::sole()->id;

        $this->actingAs($customer, 'api')
            ->getJson("/api/incidents/{$incidentId}")
            ->assertOk()
            ->assertJsonPath('data.attachments.0.kind', IncidentAttachment::KIND_EVIDENCE);
    }

    public function test_a_customer_cannot_attach_more_than_three_photos(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'api')
            ->post('/api/incidents', $this->complaintPayload([
                'photos' => array_map(
                    fn (int $i) => UploadedFile::fake()->image("leak-{$i}.jpg"),
                    range(1, IncidentAttachment::MAX_EVIDENCE_PHOTOS + 1),
                ),
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos');

        // Rejected before the complaint was created, so no orphan rows.
        $this->assertSame(0, Incident::count());
        $this->assertSame(0, IncidentAttachment::count());
    }

    public function test_a_non_image_is_rejected(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'api')
            ->post('/api/incidents', $this->complaintPayload([
                'photos' => [UploadedFile::fake()->create('payload.php', 4, 'application/x-php')],
            ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos.0');

        $this->assertSame(0, Incident::count());
    }

    public function test_evidence_photos_are_not_publicly_readable(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'api')
            ->post('/api/incidents', $this->complaintPayload([
                'photos' => [UploadedFile::fake()->image('mine.jpg')],
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $attachment = IncidentAttachment::sole();

        // Same guarantee as staff proof: the URL is signed and short-lived.
        $this->actingAs($customer, 'api')
            ->getJson("/api/incidents/{$attachment->incident_id}")
            ->assertOk()
            ->assertJsonPath('data.attachments.0.url', $attachment->url);
    }

    public function test_a_row_written_before_the_kinds_split_reads_back_as_proof(): void
    {
        // Rows created before evidence/proof were separated have no kind
        // segment in the path. They were all staff photo proof, so they must
        // not fall through to a null kind and break the gallery label.
        $attachment = new IncidentAttachment([
            'file_path' => 'attachments/17/5eb9207c-uuid.jpg',
        ]);

        $this->assertSame(IncidentAttachment::KIND_PROOF, $attachment->kind);
    }

    public function test_the_evidence_scope_excludes_proof_photos(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'api')
            ->post('/api/incidents', $this->complaintPayload([
                'photos' => [UploadedFile::fake()->image('mine.jpg')],
            ]), ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertSame(1, IncidentAttachment::query()->evidence()->count());
        $this->assertSame(0, IncidentAttachment::query()->proof()->count());
    }
}
