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
                'email'      => 'admin@maynilad.local',
                'password'   => Hash::make('admin123'),
                'role'       => User::ROLE_ADMINISTRATOR,
                'is_active'  => true,
            ]);

            $engineers = collect([
                ['Axel Brion',  'engineer.axel@maynilad.local'],
                ['Kairo Zenith', 'engineer.kairo@maynilad.local'],
                ['Lior Solven', 'engineer.lior@maynilad.local'],
                ['Lyra Vantrex', 'engineer.lyra@maynilad.local'],
            ])->map(fn ($e) => User::create([
                'full_name' => "Engr. {$e[0]}",
                'email'     => $e[1],
                'password'  => Hash::make('engineer123'),
                'role'      => User::ROLE_ENGINEER,
                'is_active' => true,
            ]));

            $teamLeaders = collect([
                ['Roberto Dela Cruz', 'tl.metering@maynilad.local',   'metering'],
                ['Kiko Valdez',       'tl.billing@maynilad.local',    'billing'],
                ['Maris Domingo',     'tl.water_quality@maynilad.local','water_quality'],
                ['Renz Cruz',         'tl.operations@maynilad.local', 'operations'],
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
            $samples = [
                ['Robert Johnson', 'Metering',  'Meter not working',                                 'Metering Issue',     'High',     1.0],
                ['Lisa Anderson',  'Billing',   'Duplicate charge received',                          'Billing Issue',      'Low',      0.3],
                ['David Wilson',   'Operations','Dirty water coming out (re-opened)',                'Water Quality Concern', 'High', 1.4],
                ['Anna Thompson',  'Operations','Low water pressure',                                'Operations Issue',   'Medium',   0.6],
                ['Robert Johnson', 'Billing',   'Billing overcharge',                                'Billing Issue',      'Medium',   0.5],
                ['Lisa Anderson',  'Operations','Water quality concern',                             'Water Quality Concern', 'High',  1.1],
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
                    'status'          => $severity === 'High' ? 'in_progress' : 'open',
                    'submitted_at'    => now()->subDays(rand(1, 10)),
                    'resolved_at'     => $severity === 'Low' ? now()->subDays(rand(0,2)) : null,
                    'created_by'      => $customer->id,
                    'updated_by'      => $customer->id,
                ]);
                $incidentIds[] = $incident->id;

                // Auto-assign to matching team leader
                $tl = $teamLeaders->firstWhere('department_team', strtolower(str_replace(' Issue','',str_replace(' Concern','',$category))));
                if ($tl) {
                    Assignment::create([
                        'incident_id'       => $incident->id,
                        'team_leader_id'    => $tl->id,
                        'assigned_at'       => $incident->submitted_at,
                        'action_status'     => $severity === 'Low' ? Assignment::ACTION_RESOLVED : Assignment::ACTION_ASSIGNED,
                        'created_by'        => $admin->id,
                        'updated_by'        => $admin->id,
                    ]);
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
            $this->command->info('  Admin:      admin@maynilad.local / admin123');
            $this->command->info('  Engineer:   engineer.axel@maynilad.local / engineer123');
            $this->command->info('  Offsite:    tl.metering@maynilad.local / staff123');
            $this->command->info('  Customer:   robert.j@example.com / customer123');
        });
    }
}
