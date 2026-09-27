<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for "which rows may this user see?".
 *
 * The web portal and the JSON API both filter through here. They used to
 * disagree — the API scoped non-engineers while the portal scoped nobody —
 * so the same rule now lives in exactly one place.
 *
 * Role capabilities (capstone role matrix):
 *   - customer       sees only their own complaints
 *   - offsite_staff  sees only complaints assigned to them
 *   - engineer       sees only complaints in their department, and no
 *                    customer accounts at all
 *   - administrator  sees everything
 */
class VisibilityScope
{
    /**
     * Category -> department mapping, mirroring DFD 3.2 in AiRoutingService.
     * Duplicated rather than imported because the routing map is private to
     * that service; keep the two in step if either changes.
     *
     * @var array<string, string>
     */
    private const CATEGORY_TO_DEPARTMENT = [
        'Billing'       => 'billing',
        'Water Quality' => 'water_quality',
        'Metering'      => 'metering',
        'Operations'    => 'operations',
    ];

    /**
     * Restrict a users query to what [User::isEngineer()] is allowed to see:
     * staff sharing their department, never a customer.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function users(Builder $query, User $actor): Builder
    {
        if (! $actor->isEngineer()) {
            return $query;
        }

        $query->where('role', '!=', User::ROLE_CUSTOMER);

        // Guard the nullable department explicitly. Left as a bare
        // where('department_team', $actor->department_team), Eloquent
        // rewrites it to `WHERE department_team IS NULL`, which matches
        // every customer row in the database.
        if ($actor->department_team === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('department_team', $actor->department_team);
    }

    /**
     * Restrict an incidents query to what [User] is allowed to see.
     *
     * @param  Builder<Incident>  $query
     * @return Builder<Incident>
     */
    public static function incidents(Builder $query, User $actor): Builder
    {
        if ($actor->isCustomer()) {
            return $query->where('customer_id', $actor->id);
        }

        if ($actor->isOffsiteStaff()) {
            return $query->whereHas(
                'assignments',
                fn (Builder $q) => $q->where('team_leader_id', $actor->id)
            );
        }

        if ($actor->isEngineer()) {
            return $query->where(function (Builder $q) use ($actor) {
                // Complaints the engineer leads personally.
                $q->whereHas(
                    'assignments',
                    fn (Builder $a) => $a->where('team_leader_id', $actor->id)
                );

                // Plus the categories their department handles, so unassigned
                // work is still visible. An engineer with no department on
                // file falls back to their own assignments only.
                $categories = self::categoriesFor($actor->department_team);

                if ($categories !== []) {
                    $q->orWhereIn('category', $categories);
                }
            });
        }

        return $query; // administrator
    }

    /**
     * Whether [User] may open this specific complaint. Guards the detail and
     * action routes, so a scoped list cannot be bypassed by typing a URL.
     */
    public static function canViewIncident(Incident $incident, User $actor): bool
    {
        return self::incidents($incident->newQuery(), $actor)
            ->whereKey($incident->getKey())
            ->exists();
    }

    /**
     * Categories routed to [department].
     *
     * @return list<string>
     */
    private static function categoriesFor(?string $department): array
    {
        if ($department === null) {
            return [];
        }

        $department = strtolower(trim($department));

        return array_keys(array_filter(
            self::CATEGORY_TO_DEPARTMENT,
            fn (string $dept) => $dept === $department
        ));
    }
}
