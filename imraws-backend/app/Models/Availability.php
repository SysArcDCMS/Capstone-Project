<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Availability model — capstone tbl_availability.
 *
 * One row per offsite staff member. Constrained by `availability_status`
 * Postgres ENUM with the four values from capstone Section 3.5
 * Evaluation Q5: available | on_duty | unavailable | on_break.
 *
 * DFD 3.4 (Filter by Availability) cross-references this table when
 * AI-driven routing picks a Team Leader.
 */
class Availability extends Model
{
    /** @use HasFactory<\Database\Factories\AvailabilityFactory> */
    use HasFactory;

    protected $table = 'tbl_availability';

    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_ON_DUTY     = 'on_duty';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_ON_BREAK    = 'on_break';

    /** @var list<string> */
    protected $fillable = [
        'staff_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return array<string, mixed> */
    public function toRoutingFilter(): array
    {
        return [
            'staff_id' => $this->staff_id,
            'status'   => $this->status,
        ];
    }
}
