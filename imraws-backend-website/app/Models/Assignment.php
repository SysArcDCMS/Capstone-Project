<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assignment model — capstone tbl_assignments.
 *
 * Each assignment routes an incident to a Team Leader. A re-assignment
 * by an engineer creates a new row (per capstone DFD 4.10 — Reassign).
 */
class Assignment extends Model
{
    /** @use HasFactory<\Database\Factories\AssignmentFactory> */
    use HasFactory;

    protected $table = 'tbl_assignments';

    public const ACTION_PENDING     = 'pending';
    public const ACTION_ASSIGNED    = 'assigned';
    public const ACTION_ACCEPT      = 'accept';
    public const ACTION_REJECT      = 'reject';
    public const ACTION_CORRECT     = 'correct';
    public const ACTION_OVERRIDE    = 'override';
    public const ACTION_REASSIGN    = 'reassign';
    public const ACTION_IN_PROGRESS = 'in_progress';
    public const ACTION_RESOLVED    = 'resolved';

    /** @var list<string> */
    protected $fillable = [
        'incident_id',
        'team_leader_id',
        'engineer_review_id',
        'assigned_at',
        'resolution_notes',
        'action_status',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function engineer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'engineer_review_id');
    }
}
