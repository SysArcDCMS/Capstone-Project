<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;
use App\Services\AiRoutingService;
use App\Services\NlpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Incident controller — capstone DFD 2.0 (Complaint Processing)
 * plus DFD 5.0 (Resolution Tracking) and DFD 3.0 (AI-Driven Routing).
 *
 * POST /api/incidents runs the full DFD 2.1 → 2.11 → 3.0 flow:
 *   - Receive Complaint Text (2.1)
 *   - Validate Customer Token (2.2)
 *   - Sanitize & Normalize (2.3)
 *   - Tokenize, Stopword, Lemmatize (2.4, 2.5)
 *   - Feature Extraction — TF-IDF (2.6)
 *   - NLP Classification (2.7)
 *   - Sentiment Analysis (2.8)
 *   - Composite Severity Score (2.9)
 *   - Assign Severity Level (2.10)
 *   - Store Incident (2.11)
 *   - Then automatically:
 *   - Retrieve Incident Data (3.1)
 *   - Map Category to Department (3.2)
 *   - Query Team Leader(s) (3.3)
 *   - Filter by Availability (3.4)
 *   - Select Primary Team Leader (3.5)
 *   - Create Assignment Record (3.6)
 *   - Update Incident Status to Assigned (3.7)
 *   - Push to Team Leader Mobile Queue (3.8)
 */
class IncidentController extends Controller
{
    public function __construct(
        private readonly NlpService $nlp,
        private readonly AiRoutingService $router,
    ) {}

    /**
     * GET /api/incidents
     *
     * Role-filtered listing per capstone DFD 1.0 scope:
     *   - customer        → only their own incidents
     *   - offsite_staff   → only incidents assigned to them (via assignment)
     *   - engineer        → all incidents (for adjudication)
     *   - administrator   → all incidents
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Incident::query()->with(['customer:id,full_name,email', 'assignments.teamLeader:id,full_name'])
            ->orderByDesc('submitted_at');

        if ($user->isCustomer()) {
            $query->where('customer_id', $user->id);
        } elseif ($user->isOffsiteStaff()) {
            $query->whereHas('assignments', fn ($q) =>
                $q->where('team_leader_id', $user->id)
            );
        }
        // engineer + administrator see everything (no extra where)

        // Optional filters
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        return response()->json(
            $this->paginated($query->paginate($perPage))
        );
    }

    /**
     * POST /api/incidents
     *
     * Customer submits a new complaint. Triggers the NLP pipeline and
     * stores the resulting {category, severity, composite_score} on
     * the incident row (capstone DFD 2.7–2.11).
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Only customers submit complaints directly (per capstone scope).
        // Engineers/Admins who create on behalf of a customer must pass
        // an explicit customer_id.
        $data = $request->validate([
            'description' => ['required', 'string', 'min:5', 'max:5000'],
            'location'    => ['nullable', 'string', 'max:500'],
            'customer_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $customerId = $user->isCustomer()
            ? $user->id
            : ($data['customer_id'] ?? null);

        if (! $customerId) {
            return response()->json([
                'message' => 'customer_id is required when the actor is not a customer.',
            ], 422);
        }

        // Compute days_pending from submitted_at (always 0 at creation).
        $daysPending = 0;

        try {
            $analysis = $this->nlp->processComplaint($data['description'], $daysPending);
        } catch (\Throwable $e) {
            // NLP failure should not lose the complaint — store with
            // category=null so an engineer can classify manually.
            AuditLog::record(
                userId:    $user->id,
                action:    'nlp_failed',
                tableName: 'tbl_incidents',
                recordId:  null,
                newValue:  ['error' => $e->getMessage()],
            );
            $analysis = [
                'category'             => null,
                'category_confidence'  => 0.0,
                'sentiment'            => 'NEUTRAL',
                'sentiment_score'      => 0.0,
                'composite_score'      => 0.0,
                'severity'             => null,
            ];
        }

        $incident = Incident::create([
            'customer_id'     => $customerId,
            'description'     => $data['description'],
            'location'        => $data['location'] ?? null,
            'category'        => $analysis['category'],
            'severity'        => $analysis['severity'],
            'composite_score' => $analysis['composite_score'],
            'status'          => Incident::STATUS_OPEN,
            'submitted_at'    => now(),
            'created_by'      => $user->id,
            'updated_by'      => $user->id,
        ]);

        AuditLog::record(
            userId:    $user->id,
            action:    'create',
            tableName: 'tbl_incidents',
            recordId:  $incident->id,
            newValue:  $incident->only(['customer_id', 'category', 'severity', 'composite_score', 'status']),
        );

        // DFD 3.0 — auto-route to a Team Leader.
        $assignment = null;
        try {
            $assignment = $this->router->route($incident);
        } catch (\Throwable $e) {
            AuditLog::record(
                userId:    $user->id,
                action:    'routing_failed',
                tableName: 'tbl_incidents',
                recordId:  $incident->id,
                newValue:  ['error' => $e->getMessage()],
            );
        }

        // DFD 5.9 — Generate Customer Notification (complaint received).
        Notification::create([
            'incident_id' => $incident->id,
            'user_id'     => $customerId,
            'message'     => "Your complaint #{$incident->id} has been received and is being processed.",
            'is_read'     => false,
            'created_by'  => $user->id,
            'updated_by'  => $user->id,
        ]);

        // HITL — no automated route was found (unclassified or no available leader):
        // alert the engineers so a human classifies and routes the complaint.
        if (! $assignment) {
            $engineers = User::where('role', User::ROLE_ENGINEER)->where('is_active', true)->pluck('id');
            foreach ($engineers as $engineerId) {
                Notification::create([
                    'incident_id' => $incident->id,
                    'user_id'     => $engineerId,
                    'message'     => "Incident #{$incident->id} is unclassified and needs manual routing.",
                    'is_read'     => false,
                    'created_by'  => $user->id,
                    'updated_by'  => $user->id,
                ]);
            }
        }

        return response()->json([
            'data' => $incident->load('customer:id,full_name,email'),
            'analysis' => $analysis,
            'assignment' => $assignment,
        ], 201);
    }

    /**
     * GET /api/incidents/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $incident = Incident::with([
            'customer:id,full_name,email',
            'assignments.teamLeader:id,full_name',
            'assignments.engineer:id,full_name',
            'feedback',
            'attachments',
            'notifications',
        ])->find($id);

        if (! $incident) {
            return response()->json(['message' => 'Incident not found.'], 404);
        }

        // Customers can only view their own incidents
        if ($user->isCustomer() && $incident->customer_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $incident]);
    }

    /**
     * PATCH /api/incidents/{id}/status
     *
     * Offsite staff and engineers update incident status
     * (capstone DFD 5.3 — Update Status to In Progress,
     * DFD 5.7 — Update Status to Resolved).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                Incident::STATUS_OPEN,
                Incident::STATUS_ASSIGNED,
                Incident::STATUS_IN_PROGRESS,
                Incident::STATUS_RESOLVED,
                Incident::STATUS_REJECTED,
            ])],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $incident = Incident::find($id);
        if (! $incident) {
            return response()->json(['message' => 'Incident not found.'], 404);
        }

        $old = $incident->only(['status', 'resolved_at']);

        $incident->status = $data['status'];
        if ($data['status'] === Incident::STATUS_RESOLVED) {
            $incident->resolved_at = now();
        }
        $incident->updated_by = $user->id;
        $incident->save();

        AuditLog::record(
            userId:    $user->id,
            action:    'update_status',
            tableName: 'tbl_incidents',
            recordId:  $incident->id,
            oldValue:  $old,
            newValue:  $incident->only(['status', 'resolved_at']),
        );

        // DFD 5.9 — Notify the customer of the new status.
        if ($incident->customer_id) {
            Notification::create([
                'incident_id' => $incident->id,
                'user_id'     => $incident->customer_id,
                'message'     => match ($incident->status) {
                    Incident::STATUS_IN_PROGRESS => "Your complaint #{$incident->id} is now being worked on.",
                    Incident::STATUS_RESOLVED    => "Your complaint #{$incident->id} has been resolved.",
                    Incident::STATUS_REJECTED    => 'Your complaint #'.$incident->id.' could not be processed'
                        . ($data['resolution_notes'] ? ": {$data['resolution_notes']}" : '.'),
                    default => "Your complaint #{$incident->id} status: {$incident->status}.",
                },
                'is_read'     => false,
                'created_by'  => $user->id,
                'updated_by'  => $user->id,
            ]);
        }

        // Notify the assigned team leader of the status change.
        $currentLeader = $incident->currentAssignment()?->team_leader_id;
        if ($currentLeader) {
            Notification::create([
                'incident_id' => $incident->id,
                'user_id'     => $currentLeader,
                'message'     => "Incident #{$incident->id} status changed to {$incident->status}.",
                'is_read'     => false,
                'created_by'  => $user->id,
                'updated_by'  => $user->id,
            ]);
        }

        return response()->json(['data' => $incident->fresh()]);
    }
}
