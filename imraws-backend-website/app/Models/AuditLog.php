<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog model — capstone tbl_audit_logs.
 *
 * Tamper-evident record of significant system events. `old_value` and
 * `new_value` are JSON snapshots that allow forensic review of every
 * classification correction, routing decision, and status change.
 */
class AuditLog extends Model
{
    /** @use HasFactory<\Database\Factories\AuditLogFactory> */
    use HasFactory;

    protected $table = 'tbl_audit_logs';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'action',
        'table_name',
        'record_id',
        'old_value',
        'new_value',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'old_value'  => 'array',
            'new_value'  => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Convenience factory used by controllers. */
    public static function record(
        ?int $userId,
        string $action,
        string $tableName,
        ?int $recordId,
        ?array $oldValue = null,
        ?array $newValue = null
    ): self {
        return self::create([
            'user_id'     => $userId,
            'action'      => $action,
            'table_name'  => $tableName,
            'record_id'   => $recordId,
            'old_value'   => $oldValue,
            'new_value'   => $newValue,
            'created_at'  => now(),
        ]);
    }
}
