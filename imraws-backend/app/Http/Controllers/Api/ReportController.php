<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Feedback;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Report / analytics controller — capstone DFD 6.0 (Report Analytics).
 *
 * Powers the admin dashboard and the engineer performance panel.
 * Per capstone Section 1.4, generates:
 *   - Incident Frequency by Category
 *   - Average Resolution Time per Department
 *   - Misclassification Trends (from HITL feedback)
 *   - Staff Availability Logs
 *   - Customer Satisfaction Indicators (placeholder — surveys out of scope)
 */
class ReportController extends Controller
{
    /**
     * GET /api/reports/dashboard
     *
     * Single payload that drives the web portal dashboard cards.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $now    = Carbon::now();
        $window = (int) $request->query('days', 30);
        $since  = $now->copy()->subDays($window);

        // ── KPI counts ───────────────────────────────────────────────
        $total = Incident::count();
        $byStatus = Incident::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $bySeverity = Incident::query()
            ->whereNotNull('severity')
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $byCategory = Incident::query()
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        // ── Average resolution time (hours) per department ──────────
        $avgResolutionByDept = Incident::query()
            ->join('tbl_assignments', 'tbl_assignments.incident_id', '=', 'tbl_incidents.id')
            ->join('users as tl', 'tl.id', '=', 'tbl_assignments.team_leader_id')
            ->whereNotNull('tbl_incidents.resolved_at')
            ->selectRaw("tl.department_team, AVG(EXTRACT(EPOCH FROM (tbl_incidents.resolved_at - tbl_incidents.submitted_at))/3600) as avg_hours, COUNT(*) as total")
            ->groupBy('tl.department_team')
            ->get()
            ->map(fn ($r) => [
                'department_team' => $r->department_team,
                'avg_hours'       => round((float) $r->avg_hours, 2),
                'total_resolved'  => (int) $r->total,
            ]);

        // ── Misclassification Trends ─────────────────────────────────
        $totalFeedback      = Feedback::count();
        $totalCorrections   = Feedback::whereColumn('original_category', '!=', 'corrected_category')
            ->whereNotNull('corrected_category')
            ->count();
        $rateOfCorrection   = $totalFeedback > 0 ? round($totalCorrections / $totalFeedback, 3) : 0.0;

        $correctionPatterns = Feedback::query()
            ->whereColumn('original_category', '!=', 'corrected_category')
            ->whereNotNull('corrected_category')
            ->selectRaw('original_category, corrected_category, COUNT(*) as count')
            ->groupBy('original_category', 'corrected_category')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // ── Staff Availability Logs ──────────────────────────────────
        $availabilityBreakdown = Availability::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // ── Recent activity (last 7 days) ───────────────────────────
        $last7Days = Incident::where('submitted_at', '>=', $now->copy()->subDays(7))
            ->selectRaw('DATE(submitted_at) as day, COUNT(*) as count')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        return response()->json([
            'data' => [
                'window_days'              => $window,
                'incidents' => [
                    'total'         => $total,
                    'by_status'     => $byStatus,
                    'by_severity'   => $bySeverity,
                    'by_category'   => $byCategory,
                    'last_7_days'   => $last7Days,
                ],
                'resolution' => [
                    'avg_hours_by_department' => $avgResolutionByDept,
                ],
                'feedback' => [
                    'total'                => $totalFeedback,
                    'corrections'          => $totalCorrections,
                    'correction_rate'      => $rateOfCorrection,
                    'top_correction_pairs' => $correctionPatterns,
                ],
                'availability' => [
                    'by_status' => $availabilityBreakdown,
                ],
            ],
        ]);
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
