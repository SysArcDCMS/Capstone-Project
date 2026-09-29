<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IncidentAttachment model — capstone tbl_incident_attachments.
 *
 * Photo proof uploaded by offsite staff upon completing a repair
 * (DFD 5.6 — Attach Photo Proof, Optional).
 *
 * The file lives on the private `attachments` disk at
 * storage/app/private/attachments/{incident_id}/{filename} and is only
 * reachable through a time-limited signed URL.
 */
class IncidentAttachment extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentAttachmentFactory> */
    use HasFactory;

    /**
     * Filesystem disk holding the photo. Configured in filesystems.php with
     * `serve => true` so signed URLs can be generated and served.
     */
    public const DISK = 'attachments';

    /**
     * How long a generated photo URL stays valid. Short enough that a URL
     * pasted into a chat or left in device logs goes stale, long enough for
     * the gallery and the full-screen viewer in one sitting.
     */
    public const URL_TTL_MINUTES = 15;

    protected $table = 'tbl_incident_attachments';

    /** @var list<string> */
    protected $fillable = [
        'incident_id',
        'uploaded_by',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'caption',
    ];

    /**
     * Without this the `url` accessor below is invisible to `toArray()`: Eloquent
     * only serialises real columns plus appended attributes, so every client
     * received `file_path` and nothing to fetch the image with.
     *
     * @var list<string>
     */
    protected $appends = ['url'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Time-limited signed URL for the photo.
     *
     * Previously this returned `Storage::disk('public')->url()`, which built
     * `{APP_URL}/storage/{path}`. That was wrong twice over:
     *
     *   1. It was a plain public path, so anyone who could guess an incident
     *      id could fetch the photo with no JWT at all.
     *   2. APP_URL is `http://localhost:8000` in development, and a signed-in
     *      phone resolves `localhost` to itself — so the image never loaded
     *      on the device that uploaded it, only "Unavailable".
     *
     * `temporaryUrl()` fixes both: the signature gates access, and the host
     * is taken from the incoming request, so a phone on the LAN receives an
     * image URL pointing at the server that actually answered the API call.
     *
     * @throws \RuntimeException if the disk is not configured with `serve`
     */
    public function getUrlAttribute(): string
    {
        return \Storage::disk(self::DISK)->temporaryUrl(
            $this->file_path,
            now()->addMinutes(self::URL_TTL_MINUTES),
        );
    }
}
