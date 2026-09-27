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
 * The file lives on the `public` disk at
 * storage/app/public/attachments/{incident_id}/{filename}.
 */
class IncidentAttachment extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentAttachmentFactory> */
    use HasFactory;

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

    /** Public URL for download. */
    public function getUrlAttribute(): string
    {
        return \Storage::disk('public')->url($this->file_path);
    }
}
