<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single outstanding password-reset code (capstone DFD 1.9).
 *
 * Only the bcrypt hash of the emailed code is kept, alongside an expiry, a
 * failed-attempt counter and a consumed marker. A new request for the same
 * address deletes the previous row, so at most one code is ever live.
 */
class PasswordResetCode extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'email',
        'code_hash',
        'expires_at',
        'attempts',
        'consumed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /** Neither already spent nor past its expiry. */
    public function isUsable(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
