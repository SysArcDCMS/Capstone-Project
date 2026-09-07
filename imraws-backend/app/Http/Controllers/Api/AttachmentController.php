<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photo proof controller — capstone DFD 5.6 (Attach Photo Proof – Optional).
 *
 * Offsite staff uploads an image to confirm completion of a field repair.
 * The file is stored at storage/app/public/attachments/{incident_id}/{uuid}.{ext}
 * and the row is persisted in tbl_incident_attachments.
 *
 * Files are served via Laravel's `public` disk, accessible at
 * /storage/attachments/{incident_id}/{file}.
 */
class AttachmentController extends Controller
{
    /** Allowed MIME types (capstone DFD 5.6 is image-only). */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    /** Max upload size: 5 MB. */
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024;

    /**
     * POST /api/incidents/{id}/attachments
     *
     * Offsite staff (must be assigned to the incident) uploads one image.
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'file'    => ['required', 'file', 'max:5120'], // 5 MB in KB
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $incident = Incident::find($id);
        if (! $incident) {
            return response()->json(['message' => 'Incident not found.'], 404);
        }

        // Offsite staff can only attach to incidents they're assigned to.
        if ($user->isOffsiteStaff()) {
            $isAssigned = $incident->assignments()
                ->where('team_leader_id', $user->id)
                ->exists();
            if (! $isAssigned) {
                return response()->json([
                    'message' => 'You are not assigned to this incident.',
                ], 403);
            }
        }
        // Engineers and administrators can attach too (for record-keeping).

        $file = $request->file('file');

        if (! in_array($file->getMimeType(), self::ALLOWED_MIME, true)) {
            return response()->json([
                'message' => 'Only JPEG, PNG, or WebP images are allowed.',
            ], 422);
        }

        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            return response()->json([
                'message' => 'File too large (max 5 MB).',
            ], 413);
        }

        // Build path: attachments/{incident_id}/{uuid}.{ext}
        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $filename  = Str::uuid()->toString().'.'.$extension;
        $path      = "attachments/{$incident->id}/{$filename}";

        Storage::disk('public')->putFileAs(
            "attachments/{$incident->id}",
            $file,
            $filename,
        );

        $attachment = IncidentAttachment::create([
            'incident_id'   => $incident->id,
            'uploaded_by'   => $user->id,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'caption'       => $request->input('caption'),
        ]);

        AuditLog::record(
            userId:    $user->id,
            action:    'upload_attachment',
            tableName: 'tbl_incident_attachments',
            recordId:  $attachment->id,
            newValue:  ['incident_id' => $incident->id, 'file_name' => $attachment->original_name],
        );

        return response()->json(['data' => $attachment], 201);
    }

    /**
     * GET /api/incidents/{id}/attachments
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $incident = Incident::find($id);
        if (! $incident) {
            return response()->json(['message' => 'Incident not found.'], 404);
        }

        /** @var User $user */
        $user = $request->user();
        if ($user->isCustomer() && $incident->customer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $incident->attachments()->with('uploader:id,full_name')->get(),
        ]);
    }

    /**
     * DELETE /api/attachments/{id}
     * Only the uploader or an admin can delete.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $attachment = IncidentAttachment::find($id);
        if (! $attachment) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        /** @var User $user */
        $user = $request->user();
        if ($attachment->uploaded_by !== $user->id && ! $user->isAdministrator()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        AuditLog::record(
            userId:    $user->id,
            action:    'delete_attachment',
            tableName: 'tbl_incident_attachments',
            recordId:  $id,
            oldValue:  ['file_path' => $attachment->file_path],
        );

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
