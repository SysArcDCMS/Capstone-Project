<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Availability;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Availability controller — capstone Section 1.4 Offsite Staff +
 * DFD 3.4 (Filter by Availability).
 *
 * Offsite staff toggle their own status ('available', 'on_duty',
 * 'unavailable', 'on_break') so the AI routing module (DFD 3.4)
 * knows who is eligible for new assignments.
 *
 * Engineers and administrators can view all availability records.
 */
class AvailabilityController extends Controller
{
    /**
     * GET /api/availability
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Availability::with('staff:id,full_name,email,department_team,is_team_leader,is_active')
            ->orderBy('updated_at', 'desc');

        if ($user->isOffsiteStaff()) {
            $query->where('staff_id', $user->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * GET /api/availability/me
     * Offsite staff shortcut for their own record.
     */
    public function me(Request $request): JsonResponse
    {
        $availability = Availability::firstOrCreate(
            ['staff_id' => $request->user()->id],
            ['status' => Availability::STATUS_UNAVAILABLE],
        );

        return response()->json(['data' => $availability]);
    }

    /**
     * POST /api/availability
     *
     * Offsite staff toggles their status. Creates the row on first call.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isOffsiteStaff()) {
            return response()->json([
                'message' => 'Only offsite staff can set availability.',
            ], 403);
        }

        $data = $request->validate([
            'status' => ['required', Rule::in([
                Availability::STATUS_AVAILABLE,
                Availability::STATUS_ON_DUTY,
                Availability::STATUS_UNAVAILABLE,
                Availability::STATUS_ON_BREAK,
            ])],
        ]);

        $old = Availability::where('staff_id', $user->id)->value('status');

        $availability = Availability::updateOrCreate(
            ['staff_id' => $user->id],
            [
                'status'     => $data['status'],
                'created_by' => $old ? Availability::where('staff_id', $user->id)->value('created_by') : $user->id,
                'updated_by' => $user->id,
            ],
        );

        AuditLog::record(
            userId:    $user->id,
            action:    'update_availability',
            tableName: 'tbl_availability',
            recordId:  $availability->id,
            oldValue:  ['status' => $old],
            newValue:  ['status' => $availability->status],
        );

        return response()->json(['data' => $availability]);
    }
}
