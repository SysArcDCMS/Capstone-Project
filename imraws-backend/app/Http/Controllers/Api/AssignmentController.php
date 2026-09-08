<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;
use App\Services\AiRoutingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Assignment controller — capstone DFD 4.0 (HITL Feedback & Adjudication)
 * plus DFD 3.0 endpoints for listing assignments.
 *
 * Team Leader (offsite_staff) actions on an assignment:
 *   - accept   (DFD 4.4 Accept Incident – Confirm Correct)
 *   - reject   (DFD 4.5 + 4.6 Reject Incident, Flag + Reason)
 *   - correct  (DFD 4.7 Correct Category and/or Severity)
 *
 * Engineer adjudication (DFD 4.10):
 *   - approve   (engineer confirms team leader's correction, or accepts AI classification)
 *   - override  (engineer imposes a third value different from AI & team leader)
 *   - reassign  (engineer reroutes to a different team leader; creates a new assignment row)
 *
 * Every action writes BOTH tbl_assignments.action_status AND a tbl_feedback
 * row tagged with the matching action_taken ENUM (Variance 3 fix).
 * Every action also writes tbl_audit_logs (capstone ERD spec).
 */
class AssignmentController extends Controller
{
    public function __construct(
        private readonly AiRoutingService $router,
    ) {}

    /**
     * GET /api/assignments
     *
     * Role-filtered:
     *   - offsite_staff (Team Leader): their own assignments
     *   - engineer / administrator:   all assignments
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Assignment::with(['incident.customer:id,full_name', 'teamLeader:id,full_name', 'engineer:id,full_name'])
            ->orderByDesc('assigned_at');

        if ($user->isOffsiteStaff()) {
            $query->where('team_leader_id', $user->id);
        }
        // engineer + administrator see all (no extra where)

        if ($status = $request->query('status')) {
            $query->where('action_status', $status);
        }

        return response()->json(
            $this->paginated($query->paginate((int) $request->query('per_page', 20)))
        );
    }

    /**
     * GET /api/assignments/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $assignment = Assignment::with(['incident', 'teamLeader:id,full_name,email', 'engineer:id,full_name,email'])
            ->find($id);

        if (! $assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        if ($user->isOffsiteStaff() && $assignment->team_leader_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $assignment]);
    }

    /**
     * POST /api/assignments/{id}/team-leader-action
     *
     * Team Leader accepts / rejects / corrects the AI classification
     * for an incident. Writes to tbl_feedback with matching action_taken.
     */
    public function teamLeaderAction(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in([
                Feedback::ACTION_ACCEPT,
                Feedback::ACTION_REJECT,
                Feedback::ACTION_CORRECT,
            ])],
            'corrected_category'  => ['nullable', 'string', 'max:32'],
            'corrected_severity'  => ['nullable', 'string', 'in:High,Medium,Low'],
            'rejection_reason'    => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $assignment = Assignment::find($id);
        if (! $assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        if ($assignment->team_leader_id !== $user->id) {
            return response()->json(['message' => 'Only the assigned team leader can act.'], 403);
        }

        $incident = Incident::find($assignment->incident_id);
        if (! $incident) {
            return response()->json(['message' => 'Incident not found.'], 404);
        }

        $oldAssignmentStatus = $assignment->action_status;

        // Determine the action_status for tbl_assignments.action_status.
        $assignment->action_status = match ($data['action']) {
            Feedback::ACTION_ACCEPT  => Assignment::ACTION_ACCEPT,
            Feedback::ACTION_REJECT  => Assignment::ACTION_REJECT,
            Feedback::ACTION_CORRECT => Assignment::ACTION_CORRECT,
        };
        $assignment->updated_by = $user->id;

        // If reject → escalate to engineer (capstone DFD 4.8)
        $notifyEngineer = false;

        if ($data['action'] === Feedback::ACTION_CORRECT) {
            // Validate at least one correction provided.
            if (empty($data['corrected_category']) && empty($data['corrected_severity'])) {
                $assignment->action_status = $oldAssignmentStatus;
                return response()->json([
                    'message' => 'corrected_category or corrected_severity is required for a correct action.',
                ], 422);
            }
            // Apply correction to the incident immediately (capstone Process 4.12 path).
            $incidentOld = $incident->only(['category', 'severity']);
            if (! empty($data['corrected_category'])) {
                $incident->category = $data['corrected_category'];
            }
            if (! empty($data['corrected_severity'])) {
                $incident->severity = $data['corrected_severity'];
            }
            $incident->updated_by = $user->id;
            $incident->save();
            $incident->auditNew = $incident->only(['category', 'severity']);
            $incident->auditOld = $incidentOld;

            $notifyEngineer = true; // escalates so engineer can confirm
        }

        $assignment->save();

        // DFD 4.11 — Log Feedback to Database
        $feedback = Feedback::create([
            'incident_id'        => $incident->id,
            'team_leader_id'     => $user->id,
            'engineer_id'        => null,
            'original_category'  => $incident->getOriginal('category'),
            'original_severity'  => $incident->getOriginal('severity'),
            'composite_score'    => $incident->composite_score,
            'action_taken'       => $data['action'],
            'corrected_category' => $data['corrected_category'] ?? null,
            'corrected_severity' => $data['corrected_severity'] ?? null,
            'rejection_reason'   => $data['rejection_reason'] ?? null,
            'final_decision'     => null,
            'feedback_timestamp' => now(),
            'review_timestamp'   => null,
            'used_for_training'  => false,
            'created_by'         => $user->id,
            'updated_by'         => $user->id,
        ]);

        AuditLog::record(
            userId:    $user->id,
            action:    "team_leader.{$data['action']}",
            tableName: 'tbl_assignments',
            recordId:  $assignment->id,
            oldValue:  ['action_status' => $oldAssignmentStatus],
            newValue:  ['action_status' => $assignment->action_status, 'feedback_id' => $feedback->id],
        );

        // DFD 4.8 — Generate Engineer Notification when rejecting or correcting.
        if ($notifyEngineer || $data['action'] === Feedback::ACTION_REJECT) {
            $engineers = User::where('role', User::ROLE_ENGINEER)->where('is_active', true)->pluck('id');
            foreach ($engineers as $engineerId) {
                Notification::create([
                    'incident_id' => $incident->id,
                    'user_id'     => $engineerId,
                    'message'     => "Incident #{$incident->id} flagged by Team Leader for adjudication ({$data['action']}).",
                    'is_read'     => false,
                    'created_by'  => $user->id,
                    'updated_by'  => $user->id,
                ]);
            }
        }

        return response()->json([
            'data' => [
                'assignment' => $assignment->fresh(),
                'feedback'   => $feedback,
            ],
        ]);
    }

    /**
     * POST /api/assignments/{id}/engineer-adjudicate
     *
     * Engineer acts on a flagged incident. Three modes (DFD 4.10):
     *   - approve   — engineer confirms team leader's correction (or accepts AI default)
     *   - override  — engineer imposes a third value
     *   - reassign  — engineer reroutes to a different team leader
     */
    public function engineerAdjudicate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'mode'                => ['required', Rule::in([
                Feedback::ACTION_ACCEPT,    // approve (using ACCEPT enum as it semantically = "approve")
                Feedback::ACTION_OVERRIDE,
                Feedback::ACTION_REASSIGN,
            ])],
            'corrected_category'  => ['nullable', 'string', 'max:32'],
            'corrected_severity'  => ['nullable', 'string', 'in:High,Medium,Low'],
            'new_team_leader_id'  => ['nullable', 'integer', Rule::exists('users', 'id')],
            'final_decision'      => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $assignment = Assignment::find($id);
        if (! $assignment) {
            return response()->json(['message' => 'Assignment not found.'], 404);
        }

        $incident = Incident::find($assignment->incident_id);
        if (! $incident) {
            return response()->json(['message' => 'Incident not found.'], 404);
        }

        // Find the latest team-leader feedback for this incident (if any).
        $tlFeedback = Feedback::where('incident_id', $incident->id)
            ->whereIn('action_taken', [Feedback::ACTION_REJECT, Feedback::ACTION_CORRECT])
            ->latest('feedback_timestamp')
            ->first();

        $oldIncident = $incident->only(['category', 'severity', 'status']);
        $oldAssignmentStatus = $assignment->action_status;

        switch ($data['mode']) {
            case Feedback::ACTION_ACCEPT:
                // APPROVE — engineer confirms team leader's correction (or accepts AI default).
                // The incident values are already applied by team_leader_action. We just log the decision.
                $assignment->action_status       = Assignment::ACTION_ACCEPT;
                $assignment->engineer_review_id  = $user->id;
                $assignment->updated_by          = $user->id;
                $assignment->save();
                $incidentActionTaken = Feedback::ACTION_ACCEPT; // maps to "approve" semantically
                $engineerCorrectedCat = $tlFeedback->corrected_category ?? $incident->category;
                $engineerCorrectedSev = $tlFeedback->corrected_severity ?? $incident->severity;
                break;

            case Feedback::ACTION_OVERRIDE:
                // OVERRIDE — engineer imposes new values.
                if (empty($data['corrected_category']) && empty($data['corrected_severity'])) {
                    return response()->json([
                        'message' => 'corrected_category or corrected_severity is required for override.',
                    ], 422);
                }
                if (! empty($data['corrected_category'])) {
                    $incident->category = $data['corrected_category'];
                }
                if (! empty($data['corrected_severity'])) {
                    $incident->severity = $data['corrected_severity'];
                }
                $incident->updated_by = $user->id;
                $incident->save();

                $assignment->action_status      = Assignment::ACTION_OVERRIDE;
                $assignment->engineer_review_id = $user->id;
                $assignment->updated_by         = $user->id;
                $assignment->save();
                $incidentActionTaken = Feedback::ACTION_OVERRIDE;
                $engineerCorrectedCat = $data['corrected_category'] ?? $incident->category;
                $engineerCorrectedSev = $data['corrected_severity'] ?? $incident->severity;
                break;

            case Feedback::ACTION_REASSIGN:
                // REASSIGN — flip old assignment, create new one.
                if (empty($data['new_team_leader_id'])) {
                    return response()->json([
                        'message' => 'new_team_leader_id is required for reassign.',
                    ], 422);
                }

                $assignment->action_status = Assignment::ACTION_REASSIGN;
                $assignment->updated_by    = $user->id;
                $assignment->save();

                $newAssignment = $this->router->reassign($incident, (int) $data['new_team_leader_id']);
                $newAssignment->engineer_review_id = $user->id;
                $newAssignment->updated_by         = $user->id;
                $newAssignment->save();

                $incidentActionTaken = Feedback::ACTION_REASSIGN;
                $engineerCorrectedCat = $incident->category;
                $engineerCorrectedSev = $incident->severity;
                break;
        }

        // DFD 4.11 — Log engineer feedback
        $feedback = Feedback::create([
            'incident_id'        => $incident->id,
            'team_leader_id'     => $assignment->team_leader_id,
            'engineer_id'        => $user->id,
            'original_category'  => $tlFeedback->corrected_category ?? $oldIncident['category'],
            'original_severity'  => $tlFeedback->corrected_severity ?? $oldIncident['severity'],
            'composite_score'    => $incident->composite_score,
            'action_taken'       => $incidentActionTaken,
            'corrected_category' => $engineerCorrectedCat,
            'corrected_severity' => $engineerCorrectedSev,
            'rejection_reason'   => null,
            'final_decision'     => $data['final_decision'] ?? "Engineer {$data['mode']} on assignment #{$assignment->id}",
            'feedback_timestamp' => now(),
            'review_timestamp'   => now(),
            'used_for_training'  => true,    // engineer decisions are gold-label
            'created_by'         => $user->id,
            'updated_by'         => $user->id,
        ]);

        AuditLog::record(
            userId:    $user->id,
            action:    "engineer.{$data['mode']}",
            tableName: 'tbl_assignments',
            recordId:  $assignment->id,
            oldValue:  ['action_status' => $oldAssignmentStatus, 'incident' => $oldIncident],
            newValue:  ['action_status' => $assignment->action_status, 'feedback_id' => $feedback->id],
        );

        // Notify customer + team leader of final decision (DFD 5.9 customer notification pattern).
        Notification::create([
            'incident_id' => $incident->id,
            'user_id'     => $incident->customer_id,
            'message'     => "Your complaint #{$incident->id} has been adjudicated: {$data['mode']}.",
            'is_read'     => false,
            'created_by'  => $user->id,
            'updated_by'  => $user->id,
        ]);

        return response()->json([
            'data' => [
                'assignment' => $assignment->fresh(),
                'feedback'   => $feedback,
            ],
        ]);
    }

    /**
     * POST /api/incidents/{id}/route
     *
     * Manually trigger AI routing on an incident (admin/engineer override).
     * Normally triggered automatically after NLP classification, but this
     * allows re-routing if a routing attempt initially failed.
     */
    public function routeIncident(Request $request, int $id): JsonResponse
    {
        $incident = Incident::find($id);
        if (! $incident) {
            return response()->json(['message' => 'Incident not found.'], 404);
        }

        $assignment = $this->router->route($incident);
        if (! $assignment) {
            return response()->json([
                'message' => 'No available team leader. Mark an offsite staff member available first.',
            ], 422);
        }

        return response()->json([
            'data' => $assignment->load(['teamLeader:id,full_name,email', 'engineer:id,full_name,email']),
        ]);
    }
}
