<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IncidentAttachment model — capstone tbl_incident_attachments.
 *
 * Two kinds of photo hang off a complaint:
 *
 *   - evidence — pictures the customer attaches when filing, showing the
 *     problem they are reporting (DFD 2.2, optional).
 *   - proof — pictures offsite staff attach after a repair, confirming the
 *     work was done (DFD 5.6, optional).
 *
 * The kind is carried by the storage path rather than a column. A `kind`
 * column would need a migration and a backfill, and the path already records
 * the same fact: `attachments/{kind}/{incident_id}/{filename}`. The defence is
 * close, so there is nothing to gain from a second source of truth that could
 * disagree with the first.
 *
 * The file lives on the private `attachments` disk under
 * storage/app/private/attachments/{kind}/{incident_id}/{filename} and is only
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

    /** Customer photo, filed with the complaint. */
    public const KIND_EVIDENCE = 'evidence';

    /** Offsite staff photo, confirming a repair. */
    public const KIND_PROOF = 'proof';

    /**
     * The path segment for each kind, and the only place the two are written
     * down. `getKindAttribute()` reads it back out of the stored path.
     *
     * @var array<string, string>
     */
    private const KIND_DIRS = [
        self::KIND_EVIDENCE => 'evidence',
        self::KIND_PROOF    => 'proof',
    ];

    /**
     * How long a generated photo URL stays valid. Short enough that a URL
     * pasted into a chat or left in device logs goes stale, long enough for
     * the gallery and the full-screen viewer in one sitting.
     */
    public const URL_TTL_MINUTES = 15;

    /**
     * MIME types a complaint photo may have, and the extension each is stored
     * under (DFD 2.2 and 5.6 are both image-only).
     *
     * The extension comes from the server-sniffed MIME rather than
     * getClientOriginalExtension(), so the stored key always matches the
     * bytes actually written. A client asking for `photo.php` gets a `.jpg`
     * key, and a mismatched extension can no longer influence how the file is
     * served back.
     *
     * @var array<string, string>
     */
    public const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /** Max size of a single photo: 5 MB. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * How many evidence photos a customer may attach while filing.
     *
     * Three is enough to show a leak from more than one angle without letting
     * one complaint turn into a photo album.
     */
    public const MAX_EVIDENCE_PHOTOS = 3;

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
     * `kind` is derived, so it has to be appended explicitly to reach clients —
     * the mobile gallery labels each photo, and it cannot infer the kind from
     * a URL it is not allowed to inspect.
     *
     * @var list<string>
     */
    protected $appends = ['url', 'kind'];

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
     * Storage path for a new photo of the given kind.
     *
     * @param  string  $kind      one of the KIND_* constants
     * @param  int     $incidentId
     * @param  string  $filename  already extensioned, uuid-based
     */
    public static function pathFor(string $kind, int $incidentId, string $filename): string
    {
        $dir = self::KIND_DIRS[$kind] ?? throw new \InvalidArgumentException(
            "Unknown attachment kind [{$kind}]."
        );

        return "attachments/{$dir}/{$incidentId}/{$filename}";
    }

    /**
     * Whether this photo is the customer's evidence or the staff's proof.
     *
     * Reads the directory segment written by pathFor(). Rows that predate the
     * split were stored as attachments/{incident_id}/{filename} with no kind
     * segment, and those were all staff photo proof, so they read back as
     * 'proof' rather than falling through to a null.
     */
    public function getKindAttribute(): string
    {
        // attachments/{kind}/{incident_id}/{filename} — the segment after
        // "attachments/" is the kind. Rows written before the split have the
        // incident id in that position instead; those were all staff photo
        // proof, so anything that is not evidence reads back as proof.
        $segment = explode('/', (string) $this->file_path)[1] ?? '';

        return $segment === self::KIND_DIRS[self::KIND_EVIDENCE]
            ? self::KIND_EVIDENCE
            : self::KIND_PROOF;
    }

    /** @param  Builder<self>  $query */
    public function scopeEvidence(Builder $query): Builder
    {
        return $query->where('file_path', 'LIKE', 'attachments/evidence/%');
    }

    /**
     * @param  Builder<self>  $query
     *
     * @return Builder<self>
     */
    public function scopeProof(Builder $query): Builder
    {
        // Only two kinds exist, so "not evidence" is "proof" — which also
        // covers the pre-split rows carrying no kind segment at all.
        return $query->where('file_path', 'NOT LIKE', 'attachments/evidence/%');
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
