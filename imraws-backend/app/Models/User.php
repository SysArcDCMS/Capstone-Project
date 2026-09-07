<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * User model — capstone tbl_users.
 *
 * Four roles (Postgres ENUM `user_role`):
 *   customer       — files complaints via the mobile app
 *   engineer       — monitors flagged incidents, adjudicates via web portal
 *   offsite_staff  — receives assignments, optionally a team leader
 *   administrator  — manages users, routing rules, sees all reports
 *
 * Field `is_team_leader` differentiates offsite staff who can receive
 * routed assignments (DFD 3.3 — Query Team Leader(s) by Department).
 */
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER       = 'customer';
    public const ROLE_ADMINISTRATOR  = 'administrator';
    public const ROLE_ENGINEER       = 'engineer';
    public const ROLE_OFFSITE_STAFF  = 'offsite_staff';

    /** @var list<string> */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'contact_no',
        'address',
        'role',
        'department_team',
        'is_active',
        'is_team_leader',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'is_team_leader'    => 'boolean',
        ];
    }

    // ── JWT ────────────────────────────────────────────────────────────

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /** @return array<string, mixed> */
    public function getJWTCustomClaims(): array
    {
        return [
            'role'            => $this->role,
            'is_team_leader'  => $this->is_team_leader,
            'department_team' => $this->department_team,
        ];
    }

    // ── Role helpers ───────────────────────────────────────────────────

    public function isCustomer(): bool       { return $this->role === self::ROLE_CUSTOMER; }
    public function isAdministrator(): bool  { return $this->role === self::ROLE_ADMINISTRATOR; }
    public function isEngineer(): bool       { return $this->role === self::ROLE_ENGINEER; }
    public function isOffsiteStaff(): bool   { return $this->role === self::ROLE_OFFSITE_STAFF; }

    // ── Relationships ──────────────────────────────────────────────────

    /** Incidents filed by this customer. */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'customer_id');
    }

    /** Assignments where this user is the assigned Team Leader. */
    public function assignmentsAsTeamLeader(): HasMany
    {
        return $this->hasMany(Assignment::class, 'team_leader_id');
    }

    /** Assignments where this user is the reviewing Engineer. */
    public function assignmentsAsEngineer(): HasMany
    {
        return $this->hasMany(Assignment::class, 'engineer_review_id');
    }

    /** Notifications received by this user (capstone DFD 5.9 / 5.10). */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /** Availability record (one per offsite staff member). */
    public function availability(): HasOne
    {
        return $this->hasOne(Availability::class, 'staff_id');
    }

    /** Feedback entries where this user acted as the team leader. */
    public function feedbackAsTeamLeader(): HasMany
    {
        return $this->hasMany(Feedback::class, 'team_leader_id');
    }

    /** Feedback entries where this user acted as the engineer. */
    public function feedbackAsEngineer(): HasMany
    {
        return $this->hasMany(Feedback::class, 'engineer_id');
    }

    /** Audit log rows where this user triggered the action. */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
