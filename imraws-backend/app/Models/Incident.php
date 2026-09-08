<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Incident model — capstone tbl_incidents.
 *
 * Each row is one customer complaint, from submission through resolution.
 * Status and severity are set by the NLP pipeline; later modified by
 * engineer adjudication per capstone DFD 4.12.
 */
class Incident extends Model
{
    /** @use HasFactory<\Database\Factories\IncidentFactory> */
    use HasFactory;

    protected $table = 'tbl_incidents';

    public const STATUS_OPEN        = 'open';
    public const STATUS_ASSIGNED    = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED    = 'resolved';
    public const STATUS_REJECTED    = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'customer_id',
        'description',
        'location',
        'category',
        'severity',
        'composite_score',
        'status',
        'submitted_at',
        'resolved_at',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'composite_score' => 'float',
            'submitted_at'    => 'datetime',
            'resolved_at'     => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    /** Customer who filed this complaint. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** All assignments created for this incident (engineer may reassign). */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'incident_id');
    }

    /** All HITL feedback entries for this incident. */
    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class, 'incident_id');
    }

    /** All notifications fired for this incident. */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'incident_id');
    }

    /** All photo proofs attached to this incident. */
    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class, 'incident_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** The currently-active assignment (latest non-reassigned row). */
    public function currentAssignment()
    {
        return $this->assignments()
            ->whereNot('action_status', 'reassign')
            ->latest('assigned_at')
            ->first();
    }
}
