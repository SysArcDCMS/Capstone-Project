<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Feedback;
use App\Models\Incident;
use Illuminate\Support\Carbon;

/**
 * Shared dashboard aggregation logic.
 * Used by both API (ReportController) and Web (PortalController).
 *
 * DFD 6.0 — Report Analytics
 */
class ReportAggregator
{
    public function dashboard(int $windowDays = 30): array
    {
        $now   = Carbon::now();
        $since = $now->copy()->subDays($windowDays);

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

        $totalFeedback    = Feedback::count();
        $totalCorrections = Feedback::whereColumn('original_category', '!=', 'corrected_category')
            ->whereNotNull('corrected_category')->count();
        $correctionRate   = $totalFeedback > 0 ? round($totalCorrections / $totalFeedback, 3) : 0.0;

        $correctionPatterns = Feedback::query()
            ->whereColumn('original_category', '!=', 'corrected_category')
            ->whereNotNull('corrected_category')
            ->selectRaw('original_category, corrected_category, COUNT(*) as count')
            ->groupBy('original_category','corrected_category')
            ->orderByDesc('count')->limit(10)->get();

        $availabilityBreakdown = Availability::query()
            ->selectRaw('status, COUNT(*) as count')->groupBy('status')
            ->pluck('count','status');

        $last7Days = Incident::where('submitted_at', '>=', $now->copy()->subDays(7))
            ->selectRaw('DATE(submitted_at) as day, COUNT(*) as count')
            ->groupBy('day')->orderBy('day')->get();

        return [
            'window_days' => $windowDays,
            'incidents' => [
                'total'       => Incident::count(),
                'by_status'   => $byStatus,
                'by_severity' => $bySeverity,
                'by_category' => $byCategory,
                'last_7_days' => $last7Days,
            ],
            'resolution' => [
                'avg_hours_by_department' => $avgResolutionByDept,
            ],
            'feedback' => [
                'total'                => $totalFeedback,
                'corrections'          => $totalCorrections,
                'correction_rate'      => $correctionRate,
                'top_correction_pairs' => $correctionPatterns,
            ],
            'availability' => [
                'by_status' => $availabilityBreakdown,
            ],
        ];
    }
}
