<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * DFD 5.6 — Attach Photo Proof.
 *
 * Photos were on the `public` disk and were returned as plain
 * `{APP_URL}/storage/...` URLs. Two things were broken by that:
 *
 *   1. Access control. The path was guessable from an incident id, so any
 *      unauthenticated request could pull a complaint photo. They can show
 *      faces, homes and addresses.
 *   2. The phone. APP_URL is `http://localhost:8000` in development, and a
 *      phone resolves `localhost` to itself, so the gallery rendered
 *      "Unavailable" for the person who had just taken the photo.
 *
 * Photos now live on a private `attachments` disk and are handed out as
 * time-limited signed URLs built from the requesting host.
 */
class AttachmentStorageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Redirect the real `attachments` disk at a scratch directory.
     *
     * `Storage::fake()` is deliberately not used: it replaces temporary URL
     * generation with a bare `?expiration=` callback
     * (Illuminate\Support\Facades\Storage::fake), so a faked disk can never
     * exercise real signing. Repointing the root keeps the genuine
     * `serve => true` local adapter, which is what the app runs on.
     */
    private function useTemporaryAttachmentDisk(): string
    {
        $root = storage_path('framework/testing/disks/attachments');

        (new Filesystem)->cleanDirectory($root);

        config(['filesystems.disks.attachments.root' => $root]);
        Storage::forgetDisk('attachments');

        return $root;
    }

    private function assignedComplaint(): array
    {
        $this->useTemporaryAttachmentDisk();

        $staff = User::factory()->offsiteStaff('metering')->create();
        $incident = Incident::factory()
            ->categorisedAs('Metering')
            ->create(['location' => 'Zone 4, Kamuning']);

        Assignment::create([
            'incident_id'    => $incident->id,
            'team_leader_id' => $staff->id,
            'action_status'  => 'resolved',
        ]);

        return [$staff, $incident];
    }

    private function uploadProof(User $staff, Incident $incident): IncidentAttachment
    {
        $this->actingAs($staff, 'api')
            ->post("/api/incidents/{$incident->id}/attachments", [
                'file' => UploadedFile::fake()->image('proof.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        return IncidentAttachment::sole();
    }

    public function test_an_uploaded_photo_lands_on_the_private_attachments_disk(): void
    {
        [$staff, $incident] = $this->assignedComplaint();

        $attachment = $this->uploadProof($staff, $incident);

        Storage::disk('attachments')->assertExists($attachment->file_path);
        $this->assertStringStartsWith("attachments/{$incident->id}/", $attachment->file_path);

        // The old public location must stay empty — that is the whole point.
        $this->assertDirectoryDoesNotExist(
            storage_path('app/public/attachments/'.$incident->id)
        );
    }

    public function test_the_stored_extension_comes_from_the_sniffed_type_not_the_filename(): void
    {
        [$staff, $incident] = $this->assignedComplaint();

        $this->actingAs($staff, 'api')
            ->post("/api/incidents/{$incident->id}/attachments", [
                // A client asking for a .php name must not get a .php object key.
                'file' => UploadedFile::fake()->create('shell.php', 4, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $attachment = IncidentAttachment::sole();
        $this->assertStringEndsWith('.jpg', $attachment->file_path);
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertSame('shell.php', $attachment->original_name, 'the real name is still recorded');
    }

    public function test_a_non_image_is_still_rejected(): void
    {
        [$staff, $incident] = $this->assignedComplaint();

        $this->actingAs($staff, 'api')
            ->post("/api/incidents/{$incident->id}/attachments", [
                'file' => UploadedFile::fake()->create('payload.php', 4, 'application/x-php'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame(0, IncidentAttachment::count());
    }

    public function test_the_url_is_signed_and_uses_the_host_that_made_the_request(): void
    {
        [$staff, $incident] = $this->assignedComplaint();
        $this->uploadProof($staff, $incident);

        // Point the URL generator at the request the phone actually made.
        // Each real HTTP request boots a fresh app, so the generator is
        // already bound to the incoming request; a test reuses one app
        // instance, so bind it explicitly. `setRequest()` also clears the
        // generator's cached root, which is what would otherwise pin the URL
        // to APP_URL. (Do not use `forgetInstance('url')` here — resolving
        // the generator again re-enters the router and recurses until the
        // process overflows the stack.)
        URL::setRequest(Request::create(
            "/api/incidents/{$incident->id}/attachments",
            'GET',
            server: ['HTTP_HOST' => '192.168.254.115:8000'],
        ));

        $url = IncidentAttachment::sole()->url;

        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
        $this->assertStringStartsWith('http://192.168.254.115:8000/storage/', $url);
        $this->assertStringNotContainsString('localhost', $url, 'the URL must not fall back to APP_URL');
    }

    public function test_the_listing_hands_out_a_url_bound_to_the_calling_host(): void
    {
        [$staff, $incident] = $this->assignedComplaint();
        $this->uploadProof($staff, $incident);

        URL::setRequest(Request::create(
            "/api/incidents/{$incident->id}/attachments",
            'GET',
            server: ['HTTP_HOST' => '192.168.254.115:8000'],
        ));

        $response = $this->actingAs($staff, 'api')
            ->getJson("/api/incidents/{$incident->id}/attachments")
            ->assertOk();

        $this->assertStringStartsWith(
            'http://192.168.254.115:8000/storage/',
            $response->json('data.0.url')
        );
    }

    public function test_the_signed_url_serves_the_photo_and_an_unsigned_one_does_not(): void
    {
        [$staff, $incident] = $this->assignedComplaint();
        $attachment = $this->uploadProof($staff, $incident);

        $signed = $attachment->url;

        $this->get($signed)->assertOk();

        // Strip the signature: this is what someone gets by guessing the path.
        $this->get(strtok($signed, '?'))->assertForbidden();

        // And altering the signature must not work either.
        $this->get($signed.'x')->assertForbidden();
    }

    public function test_the_signed_url_expires_after_the_documented_window(): void
    {
        [$staff, $incident] = $this->assignedComplaint();
        $attachment = $this->uploadProof($staff, $incident);

        parse_str((string) parse_url($attachment->url, PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('expires', $query);
        $this->assertSame(
            IncidentAttachment::URL_TTL_MINUTES * 60,
            (int) $query['expires'] - time(),
            'the window is exactly the documented TTL'
        );
    }

    public function test_deleting_a_photo_removes_the_file_from_the_private_disk(): void
    {
        [$staff, $incident] = $this->assignedComplaint();
        $attachment = $this->uploadProof($staff, $incident);
        $path = $attachment->file_path;

        $this->actingAs($staff, 'api')
            ->deleteJson("/api/attachments/{$attachment->id}")
            ->assertOk();

        Storage::disk('attachments')->assertMissing($path);
        $this->assertSame(0, IncidentAttachment::count());
    }
}
