<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feedback model — capstone tbl_feedback.
 *
 * Captures every HITL correction (team leader accept/reject/correct) and
 * every engineer adjudication (approve/override/reassign). The
 * `action_taken` column is a Postgres ENUM.
 *
 * Rows where `used_for_training = true` are the gold-label samples used
 * to retrain the NLP classifier (see ai-nlp/services/retrain_service.py).
 */
class Feedback extends Model
{
    /** @use HasFactory<\Database\Factories\FeedbackFactory> */
    use HasFactory;

    protected $table = 'tbl_feedback';

    public const ACTION_ACCEPT   = 'accept';
    public const ACTION_REJECT   = 'reject';
    public const ACTION_CORRECT  = 'correct';
    public const ACTION_OVERRIDE = 'override';
    public const ACTION_REASSIGN = 'reassign';

    /** @var list<string> */
    protected $fillable = [
        'incident_id',
        'team_leader_id',
        'engineer_id',
        'original_category',
        'original_severity',
        'composite_score',
        'action_taken',
        'corrected_category',
        'corrected_severity',
        'rejection_reason',
        'final_decision',
        'feedback_timestamp',
        'review_timestamp',
        'used_for_training',
        'created_by',
        'updated_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'composite_score'     => 'float',
            'used_for_training'   => 'boolean',
            'feedback_timestamp'  => 'datetime',
            'review_timestamp'    => 'datetime',
        ];
    }

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
        return $this->belongsTo(User::class, 'engineer_id');
    }
}
