<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Services\ReportAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Report / analytics controller — capstone DFD 6.0 (Report Analytics).
 *
 * Powers the admin dashboard and the engineer performance panel.
 * Delegates the aggregation logic to App\Services\ReportAggregator
 * so the web portal Blade views use the same numbers.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportAggregator $aggregator,
    ) {}

    /**
     * GET /api/reports/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $window = (int) $request->query('days', 30);
        return response()->json(['data' => $this->aggregator->dashboard($window)]);
    }

    /**
     * GET /api/reports/incidents
     * Filterable incident listing for admin reports page.
     */
    public function incidents(Request $request): JsonResponse
    {
        $query = Incident::with(['customer:id,full_name', 'assignments.teamLeader:id,full_name,department_team'])
            ->orderByDesc('submitted_at');

        foreach (['status', 'category', 'severity'] as $field) {
            if ($v = $request->query($field)) {
                $query->where($field, $v);
            }
        }
        if ($from = $request->query('from')) {
            $query->whereDate('submitted_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('submitted_at', '<=', $to);
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 50))]);
    }

    /**
     * GET /api/reports/audit-logs
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $query = \App\Models\AuditLog::with('user:id,full_name,role')
            ->orderByDesc('created_at');

        if ($action = $request->query('action')) {
            $query->where('action', 'like', "%{$action}%");
        }
        if ($table = $request->query('table_name')) {
            $query->where('table_name', $table);
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 50))]);
    }
}
