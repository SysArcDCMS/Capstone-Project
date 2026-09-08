<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Feedback;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data seeder — populates the system with realistic users matching
 * the capstone UI mockups:
 *
 *   Malupiton Admin          (administrator)
 *   Engr. Axel Brion         (engineer)
 *   Engr. Kairo Zenith       (engineer)
 *   Engr. Lior Solven        (engineer)
 *   Engr. Lyra Vantrex       (engineer)
 *   Roberto Dela Cruz        (offsite_staff team leader, metering)
 *   Kiko Valdez              (offsite_staff team leader, billing)
 *   Maris Domingo            (offsite_staff team leader, water_quality)
 *   Renz Cruz                (offsite_staff team leader, operations)
 *   Robert Johnson           (customer)
 *   Lisa Anderson            (customer)
 *   Anna Thompson            (customer)
 *   David Wilson             (customer)
 *
 * Plus 8 incidents spread across all 4 categories and 3 severities.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // ── Users ─────────────────────────────────────────────────
            $admin = User::create([
                'full_name'  => 'Malupiton Admin',
                'email'      => 'admin@imraws.local',
                'password'   => Hash::make('admin123'),
                'role'       => User::ROLE_ADMINISTRATOR,
                'is_active'  => true,
            ]);

            $engineers = collect([
                ['Axel Brion',  'engineer.axel@imraws.local',   'billing'],
                ['Kairo Zenith','engineer.kairo@imraws.local',  'metering'],
                ['Lior Solven', 'engineer.lior@imraws.local',   'water_quality'],
                ['Lyra Vantrex','engineer.lyra@imraws.local',   'operations'],
            ])->map(fn ($e) => User::create([
                'full_name'       => "Engr. {$e[0]}",
                'email'           => $e[1],
                'password'        => Hash::make('engineer123'),
                'role'            => User::ROLE_ENGINEER,
                'department_team' => $e[2],
                'is_active'       => true,
            ]));

            $teamLeaders = collect([
                ['Roberto Dela Cruz', 'tl.metering@imraws.local',   'metering'],
                ['Kiko Valdez',       'tl.billing@imraws.local',    'billing'],
                ['Maris Domingo',     'tl.water_quality@imraws.local','water_quality'],
                ['Renz Cruz',         'tl.operations@imraws.local', 'operations'],
            ])->map(fn ($tl) => User::create([
                'full_name'       => $tl[0],
                'email'           => $tl[1],
                'password'        => Hash::make('staff123'),
                'role'            => User::ROLE_OFFSITE_STAFF,
                'is_team_leader'  => true,
                'department_team' => $tl[2],
                'is_active'       => true,
            ]));

            $customers = collect([
                ['Robert Johnson', 'robert.j@example.com'],
                ['Lisa Anderson',  'lisa.a@example.com'],
                ['Anna Thompson',  'anna.t@example.com'],
                ['David Wilson',   'david.w@example.com'],
            ])->map(fn ($c) => User::create([
                'full_name' => $c[0],
                'email'     => $c[1],
                'password'  => Hash::make('customer123'),
                'role'      => User::ROLE_CUSTOMER,
                'is_active' => true,
            ]));

            // ── Availability — make all team leaders available/on-duty ──
            foreach ($teamLeaders as $i => $tl) {
                Availability::create([
                    'staff_id'    => $tl->id,
                    'status'      => $i % 2 === 0
                        ? Availability::STATUS_AVAILABLE
                        : Availability::STATUS_ON_DUTY,
                    'created_by'  => $admin->id,
                    'updated_by'  => $admin->id,
                ]);
            }

            // ── Incidents (sample of the 18 in the UI mockup) ──────────
            // Categories are the canonical ai-nlp labels (VAL-CATEGORIES in
            // ai-nlp/app/services/retrain_service.py) so dept lookup, filters
            // and the dashboard chart all stay consistent.
            $deptMap = [
                'Billing'       => 'billing',
                'Water Quality' => 'water_quality',
                'Metering'      => 'metering',
                'Operations'    => 'operations',
            ];

            $samples = [
                ['Robert Johnson', 'Metering',      'Meter not working',                 'Metering',      'High',   1.0],
                ['Lisa Anderson',  'Billing',       'Duplicate charge received',         'Billing',       'Low',    0.3],
                ['David Wilson',   'Operations',    'Dirty water coming out (re-opened)','Water Quality', 'High',   1.4],
                ['Anna Thompson',  'Operations',    'Low water pressure',                'Operations',    'Medium', 0.6],
                ['Robert Johnson', 'Billing',       'Billing overcharge',                'Billing',       'Medium', 0.5],
                ['Lisa Anderson',  'Operations',    'Water quality concern',             'Water Quality', 'High',   1.1],
            ];

            $incidentIds = [];
            foreach ($samples as [$customerName, $dept, $subject, $category, $severity, $composite]) {
                $customer = $customers->firstWhere('full_name', $customerName);
                $incident = Incident::create([
                    'customer_id'     => $customer->id,
                    'description'     => $subject,
                    'location'        => 'Brgy '.(100 + $customer->id).', Caloocan City',
                    'category'        => $category,
                    'severity'        => $severity,
                    'composite_score' => $composite,
                    'status'          => 'open',
                    'submitted_at'    => now()->subDays(rand(1, 10)),
                    'resolved_at'     => $severity === 'Low' ? now()->subDays(rand(0,2)) : null,
                    'created_by'      => $customer->id,
                    'updated_by'      => $customer->id,
                ]);
                $incidentIds[] = $incident->id;

                // Auto-assign to matching team leader
                $tl = $teamLeaders->firstWhere('department_team', $deptMap[$category] ?? strtolower($category));
                if ($tl) {
                    Assignment::create([
                        'incident_id'       => $incident->id,
                        'team_leader_id'    => $tl->id,
                        'assigned_at'       => $incident->submitted_at,
                        'action_status'     => $severity === 'Low' ? Assignment::ACTION_RESOLVED : Assignment::ACTION_ASSIGNED,
                        'created_by'        => $admin->id,
                        'updated_by'        => $admin->id,
                    ]);

                    // Routed incidents sit at "assigned" until field work begins (DFD 3.7 / 5.3)
                    if ($severity !== 'Low') {
                        $incident->status = Incident::STATUS_ASSIGNED;
                        $incident->updated_by = $admin->id;
                        $incident->save();
                    }
                }
            }

            // One sample feedback — team leader corrects a classification
            if (count($incidentIds) > 0) {
                Feedback::create([
                    'incident_id'        => $incidentIds[0],
                    'team_leader_id'     => $teamLeaders->firstWhere('department_team', 'metering')->id,
                    'engineer_id'        => null,
                    'original_category'  => 'Operations',
                    'original_severity'  => 'Medium',
                    'composite_score'    => 0.6,
                    'action_taken'       => Feedback::ACTION_CORRECT,
                    'corrected_category' => 'Metering',
                    'corrected_severity' => 'High',
                    'rejection_reason'   => null,
                    'final_decision'     => null,
                    'feedback_timestamp' => now()->subDays(2),
                    'review_timestamp'   => null,
                    'used_for_training'  => false,
                    'created_by'         => $teamLeaders->firstWhere('department_team','metering')->id,
                    'updated_by'         => $teamLeaders->firstWhere('department_team','metering')->id,
                ]);
            }

            $this->command->info('Demo data seeded successfully.');
            $this->command->info('');
            $this->command->info('Login credentials:');
            $this->command->info('  Admin:      admin@imraws.local / admin123');
            $this->command->info('  Engineer:   engineer.axel@imraws.local / engineer123');
            $this->command->info('  Offsite:    tl.metering@imraws.local / staff123');
            $this->command->info('  Customer:   robert.j@example.com / customer123');
        });
    }
}
