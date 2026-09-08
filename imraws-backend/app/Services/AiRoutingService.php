<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;

/**
 * AI-Driven Routing — capstone DFD Level 2 Process 3.0.
 *
 *   3.1 Retrieve Incident Data
 *   3.2 Map Incident Category to Department
 *   3.3 Query Team Leader(s) by Department
 *   3.4 Filter by Availability
 *   3.5 Select Primary Team Leader
 *   3.6 Create Assignment Record
 *   3.7 Update Incident Status to Assigned
 *   3.8 Push to Team Leader Mobile Queue
 *
 * Department is mapped from category. Team leader must be:
 *   - role = offsite_staff
 *   - is_team_leader = true
 *   - department_team matches the category's department
 *   - availability.status IN ('available', 'on_duty')
 *   - is_active = true
 *
 * If no leader matches, the first available offsite staff (any dept)
 * is selected as a fallback so the assignment never silently fails.
 */
class AiRoutingService
{
    /** Capstone category -> department mapping (DFD 3.2). */
    private const CATEGORY_TO_DEPARTMENT = [
        'Billing'       => 'billing',
        'Water Quality' => 'water_quality',
        'Metering'      => 'metering',
        'Operations'    => 'operations',
    ];

    public function route(Incident $incident): ?Assignment
    {
        if (! $incident->category) {
            return null;
        }

        $department = self::CATEGORY_TO_DEPARTMENT[$incident->category]
            ?? strtolower(str_replace(' ', '_', $incident->category));

        // Step 3.3: query team leaders in department.
        // Step 3.4: filter by availability.
        $leader = User::query()
            ->where('role', User::ROLE_OFFSITE_STAFF)
            ->where('is_team_leader', true)
            ->where('is_active', true)
            ->where('department_team', $department)
            ->whereHas('availability', function ($q) {
                $q->whereIn('status', [
                    Availability::STATUS_AVAILABLE,
                    Availability::STATUS_ON_DUTY,
                ]);
            })
            // Round-robin: pick the leader with fewest active assignments.
            ->withCount(['assignmentsAsTeamLeader as active_count' => function ($q) {
                $q->whereNotIn('action_status', [
                    Assignment::ACTION_RESOLVED,
                    Assignment::ACTION_REJECT,
                    Assignment::ACTION_REASSIGN,
                ]);
            }])
            ->orderBy('active_count')
            ->orderBy('id') // tiebreaker
            ->first();

        // Fallback: any active offsite staff (any department) who is available.
        if (! $leader) {
            $leader = User::query()
                ->where('role', User::ROLE_OFFSITE_STAFF)
                ->where('is_active', true)
                ->whereHas('availability', function ($q) {
                    $q->whereIn('status', [
                        Availability::STATUS_AVAILABLE,
                        Availability::STATUS_ON_DUTY,
                    ]);
                })
                ->orderBy('id')
                ->first();
        }

        if (! $leader) {
            return null;
        }

        return $this->createAssignment($incident, $leader->id);
    }

    /**
     * Manually re-route to a specific team leader (engineer Reassign action).
     */
    public function reassign(Incident $incident, int $newTeamLeaderId): Assignment
    {
        return $this->createAssignment(
            incident:       $incident,
            teamLeaderId:   $newTeamLeaderId,
            engineerReview: $incident->assignmentsAsTeamLeader()->latest('assigned_at')->first()?->engineer_review_id
                             ?? auth()->id(),
        );
    }

    private function createAssignment(
        Incident $incident,
        int $teamLeaderId,
        ?int $engineerReview = null,
    ): Assignment {
        // Step 3.6: Create Assignment Record
        $assignment = Assignment::create([
            'incident_id'        => $incident->id,
            'team_leader_id'     => $teamLeaderId,
            'engineer_review_id' => $engineerReview,
            'assigned_at'        => now(),
            'action_status'      => Assignment::ACTION_ASSIGNED,
            'created_by'         => auth()->id(),
            'updated_by'         => auth()->id(),
        ]);

        // Step 3.7: Update Incident Status to Assigned
        if ($incident->status === Incident::STATUS_OPEN) {
            $incident->status = Incident::STATUS_ASSIGNED;
            $incident->updated_by = auth()->id();
            $incident->save();
        }

        // Step 3.8: Push to Team Leader Queue — persist notification row
        Notification::create([
            'incident_id' => $incident->id,
            'user_id'     => $teamLeaderId,
            'message'     => "New incident #{$incident->id} assigned to you ({$incident->category}, {$incident->severity}).",
            'is_read'     => false,
            'created_by'  => auth()->id(),
            'updated_by'  => auth()->id(),
        ]);

        return $assignment;
    }
}
