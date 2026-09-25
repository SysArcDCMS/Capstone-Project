<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Incident;
use App\Models\Notification;
use App\Models\User;
use App\Services\NlpService;
use App\Services\ReportAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Web portal controller — drives all Blade pages for Engineers + Administrators.
 *
 * Authentication: Laravel session (NOT JWT). Users log in via /login and
 * their session is stored server-side. The web portal is separate from
 * the mobile-app JWT flow documented in routes/api.php.
 */
class PortalController extends Controller
{
    // Middleware is applied per-route in routes/web.php (auth:web / guest:web).
    // Constructors in regular Controllers don't have $this->middleware() in modern Laravel.

    // ── Auth ──────────────────────────────────────────────────────────

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLocked()) {
            return back()->withErrors([
                'email' => 'Account locked due to too many failed attempts. '
                    ."Try again in {$user->locked_until->diffInMinutes(now())} minute(s).",
            ])->withInput();
        }

        if (! Auth::guard('web')->attempt($credentials)) {
            $user?->registerFailedLogin();
            $remaining = $user
                ? max(0, User::MAX_LOGIN_ATTEMPTS - ($user->failed_login_attempts ?? 0))
                : 0;
            return back()->withErrors([
                'email' => $remaining > 0
                    ? "Invalid credentials. {$remaining} more failed attempt(s) before the account is locked for ".User::LOCKOUT_MINUTES.' minutes.'
                    : 'Invalid credentials. Account locked for '.User::LOCKOUT_MINUTES.' minutes.',
            ])->withInput();
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        // Only engineers/admins/offsite staff may use the web portal.
        if ($user->isCustomer()) {
            Auth::guard('web')->logout();
            return back()->withErrors([
                'email' => 'Customers use the mobile app. This portal is for Engineers and Administrators.',
            ])->withInput();
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            return back()->withErrors(['email' => 'Account deactivated.'])->withInput();
        }

        $user->resetLoginAttempts();
        AuditLog::record($user->id, 'web.login', 'users', $user->id);
        $request->session()->regenerate();
        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        if ($user) {
            AuditLog::record($user->id, 'web.logout', 'users', $user->id);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    // ── Dashboard ────────────────────────────────────────────────────

    public function dashboard()
    {
        $data = (new ReportAggregator())->dashboard();
        return view('dashboard', ['data' => $data]);
    }

    // ── Complaints ───────────────────────────────────────────────────

    public function complaints(Request $request)
    {
        $query = Incident::with(['customer:id,full_name', 'assignments.teamLeader:id,full_name'])
            ->orderByDesc('submitted_at');

        foreach (['status','severity','category'] as $f) {
            if ($v = $request->query($f)) {
                $query->where($f, $v);
            }
        }

        return view('complaints.index', ['complaints' => $query->paginate(20)]);
    }

    public function complaintShow(int $id)
    {
        $incident = Incident::with(['customer','assignments.teamLeader','feedback','attachments'])
            ->findOrFail($id);
        return view('complaints.show', ['incident' => $incident]);
    }

    /**
     * GET /complaints/{id}/modal
     *
     * JSON payload for the complaint action modal: incident summary, the
     * current assignment and the list of routable team leaders. Leaders
     * are filtered to the incident category's department when one exists.
     */
    public function complaintModal(int $id): JsonResponse
    {
        $incident = Incident::with(['customer:id,full_name,email', 'assignments.teamLeader:id,full_name,department_team'])
            ->findOrFail($id);

        $department = ['billing','metering','water_quality','operations'];
        $leaders = User::query()
            ->where('role', User::ROLE_OFFSITE_STAFF)
            ->where('is_team_leader', true)
            ->where('is_active', true)
            ->withCount(['assignmentsAsTeamLeader as active_assignments' => function ($q) {
                $q->whereNotIn('action_status', [
                    \App\Models\Assignment::ACTION_RESOLVED,
                    \App\Models\Assignment::ACTION_REJECT,
                    \App\Models\Assignment::ACTION_REASSIGN,
                ]);
            }])
            ->orderBy('department_team')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'department_team']);

        $current = $incident->currentAssignment();

        return response()->json([
            'data' => [
                'id'          => $incident->id,
                'code'        => 'C'.str_pad($incident->id, 3, '0', STR_PAD_LEFT),
                'description' => $incident->description,
                'category'    => $incident->category,
                'severity'    => $incident->severity,
                'status'      => $incident->status,
                'location'    => $incident->location,
                'submitted_at'=> $incident->submitted_at?->toDateTimeString(),
                'customer'    => $incident->customer?->full_name,
                'current'     => $current
                    ? ['team_leader' => $current->teamLeader?->full_name, 'action_status' => $current->action_status]
                    : null,
                'team_leaders' => $leaders,
            ],
        ]);
    }

    /**
     * PATCH /complaints/{id}/status
     *
     * Ajax status update from the action modal. Resolving sets resolved_at;
     * reopening clears it. Customers are notified of the change.
     */
    public function complaintUpdateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([
                Incident::STATUS_OPEN,
                Incident::STATUS_ASSIGNED,
                Incident::STATUS_IN_PROGRESS,
                Incident::STATUS_RESOLVED,
                Incident::STATUS_REJECTED,
            ])],
        ]);

        $incident = Incident::findOrFail($id);
        $old = $incident->only(['status', 'resolved_at']);

        $incident->status = $data['status'];
        $incident->resolved_at = $data['status'] === Incident::STATUS_RESOLVED ? now() : null;
        $incident->updated_by = auth()->id();
        $incident->save();

        AuditLog::record(auth()->id(), 'web.update_status', 'tbl_incidents', $incident->id,
            oldValue: $old, newValue: $incident->only(['status', 'resolved_at']));

        $this->notifyStatusChange($incident, $data['status'] ?? null);

        return response()->json([
            'message' => 'Status updated to '.str_replace('_', ' ', $incident->status).'.',
            'data'    => $incident->only(['id', 'status', 'resolved_at']),
        ]);
    }

    /**
     * POST /complaints/{id}/route
     *
     * Manually (re)route a complaint to a specific team leader. Creates a
     * new assignment row and flips the incident to "assigned".
     */
    public function complaintRoute(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'team_leader_id' => ['required', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) =>
                    $q->where('role', User::ROLE_OFFSITE_STAFF)->where('is_team_leader', true)->where('is_active', true)),
            ],
        ]);

        $incident = Incident::findOrFail($id);
        $assignment = (new \App\Services\AiRoutingService())
            ->reassign($incident, $data['team_leader_id']);

        AuditLog::record(auth()->id(), 'web.route', 'tbl_assignments', $assignment->id,
            newValue: ['incident_id' => $incident->id, 'team_leader_id' => $data['team_leader_id']]);

        return response()->json([
            'message' => "Complaint #{$incident->id} routed to ".($assignment->teamLeader?->full_name ?? 'team leader').'.',
            'data'    => [
                'id'              => $incident->id,
                'status'          => $incident->status,
                'assignment_id'   => $assignment->id,
                'team_leader_id'  => $assignment->team_leader_id,
            ],
        ]);
    }

    /**
     * POST /complaints/{id}/resolve
     *
     * Resolve with optional resolution notes. Marks the active assignment
     * as resolved too, so the team leader queue reflects completion.
     */
    public function complaintResolve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $incident = Incident::findOrFail($id);
        $old = $incident->only(['status', 'resolved_at']);

        $incident->status      = Incident::STATUS_RESOLVED;
        $incident->resolved_at = now();
        $incident->updated_by  = auth()->id();
        $incident->save();

        $assignment = $incident->currentAssignment();
        if ($assignment) {
            $assignment->action_status   = \App\Models\Assignment::ACTION_RESOLVED;
            $assignment->resolution_notes = $data['resolution_notes'] ?? null;
            $assignment->updated_by       = auth()->id();
            $assignment->save();
        }

        AuditLog::record(auth()->id(), 'web.resolve', 'tbl_incidents', $incident->id,
            oldValue: $old, newValue: $incident->only(['status', 'resolved_at']));

        $this->notifyStatusChange($incident, $data['resolution_notes'] ?? null);

        return response()->json([
            'message' => "Complaint #{$incident->id} resolved.",
            'data'    => $incident->only(['id', 'status', 'resolved_at']),
        ]);
    }

    /**
     * Notify the customer (and current team leader) about a status change.
     */
    private function notifyStatusChange(Incident $incident, ?string $note = null): void
    {
        if ($incident->customer_id) {
            Notification::create([
                'incident_id' => $incident->id,
                'user_id'     => $incident->customer_id,
                'message'     => match ($incident->status) {
                    Incident::STATUS_IN_PROGRESS => "Your complaint #{$incident->id} is now being worked on.",
                    Incident::STATUS_RESOLVED    => "Your complaint #{$incident->id} has been resolved."
                        . ($note ? ' Note: '.$note : ''),
                    Incident::STATUS_REJECTED    => "Your complaint #{$incident->id} could not be processed.",
                    default => "Your complaint #{$incident->id} status: {$incident->status}.",
                },
                'is_read'     => false,
                'created_by'  => auth()->id(),
                'updated_by'  => auth()->id(),
            ]);
        }

        $currentLeader = $incident->currentAssignment()?->team_leader_id;
        if ($currentLeader) {
            Notification::create([
                'incident_id' => $incident->id,
                'user_id'     => $currentLeader,
                'message'     => "Incident #{$incident->id} status changed to {$incident->status}.",
                'is_read'     => false,
                'created_by'  => auth()->id(),
                'updated_by'  => auth()->id(),
            ]);
        }
    }

    public function complaintsExport()
    {
        // Simple CSV export
        $rows = Incident::with(['customer:id,full_name'])->orderByDesc('submitted_at')->get();
        $filename = 'complaints_'.now()->format('Ymd_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];
        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Customer','Category','Severity','Status','Location','Submitted At']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    'C'.str_pad($r->id,3,'0',STR_PAD_LEFT),
                    $r->customer->full_name ?? '',
                    $r->category ?? '',
                    $r->severity ?? '',
                    $r->status,
                    $r->location ?? '',
                    $r->submitted_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }

    // ── Users ────────────────────────────────────────────────────────

    public function users(Request $request)
    {
        $query = User::orderByDesc('created_at');
        if ($r = $request->query('role')) $query->where('role', $r);
        if ($q = $request->query('q')) {
            $query->where(fn($w) => $w->where('full_name','ilike',"%$q%")->orWhere('email','ilike',"%$q%"));
        }
        $users = $query->paginate(20);

        $stats = [
            'total'   => User::count(),
            'active'  => User::where('is_active', true)->count(),
            'by_role' => User::selectRaw('role, COUNT(*) as c')->groupBy('role')->pluck('c','role'),
        ];

        return view('users.index', ['users' => $users, 'stats' => $stats]);
    }

    public function userCreate()
    {
        return view('users.form', ['user' => null]);
    }

    public function userStore(Request $request)
    {
        $data = $request->validate([
            'full_name'       => ['required','string','max:120'],
            'email'           => ['required','email','unique:users,email'],
            'password'        => ['required','string','min:8'],
            'role'            => ['required', Rule::in(['customer','administrator','engineer','offsite_staff'])],
            'contact_no'      => ['nullable','string','max:32'],
            'address'         => ['nullable','string','max:500'],
            'department_team' => [
                'nullable',
                Rule::in(['billing','metering','water_quality','operations']),
                Rule::requiredIf(in_array($request->input('role'), ['offsite_staff', 'engineer'])),
            ],
            'is_team_leader'  => ['boolean'],
        ]);
        $data['password'] = Hash::make($data['password']);
        if (! in_array($data['role'], ['offsite_staff', 'engineer'])) {
            $data['department_team'] = null;
        }
        $user = User::create($data + ['is_active' => true]);
        AuditLog::record(auth()->id(), 'admin.create_user', 'users', $user->id, newValue: ['role' => $user->role]);
        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function userEdit(int $id)
    {
        $user = User::findOrFail($id);
        return view('users.form', ['user' => $user]);
    }

    public function userUpdate(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'full_name'       => ['required','string','max:120'],
            'role'            => ['required', Rule::in(['customer','administrator','engineer','offsite_staff'])],
            'contact_no'      => ['nullable','string','max:32'],
            'address'         => ['nullable','string','max:500'],
            'department_team' => [
                'nullable',
                Rule::in(['billing','metering','water_quality','operations']),
                Rule::requiredIf(in_array($request->input('role'), ['offsite_staff', 'engineer'])),
            ],
            'is_team_leader'  => ['boolean'],
        ]);
        if (! in_array($data['role'], ['offsite_staff', 'engineer'])) {
            $data['department_team'] = null;
        }
        $old = $user->only(['full_name','role','department_team','is_team_leader','is_active']);
        $user->fill($data)->save();
        AuditLog::record(auth()->id(), 'admin.update_user', 'users', $user->id, oldValue: $old, newValue: $user->only(['full_name','role','department_team','is_team_leader']));
        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function userDeactivate(int $id)
    {
        $user = User::findOrFail($id);
        $newStatus = ! $user->is_active;
        $user->is_active  = $newStatus;
        $user->updated_by = auth()->id();
        $user->save();
        AuditLog::record(auth()->id(), $newStatus?'admin.reactivate_user':'admin.deactivate_user', 'users', $user->id, oldValue: ['is_active' => ! $newStatus], newValue: ['is_active' => $newStatus]);
        return redirect()->route('users.index')->with('success', $newStatus ? 'User reactivated.' : 'User deactivated.');
    }

    // ── Categories ───────────────────────────────────────────────────

    public function categories()
    {
        $categories = Category::orderByDesc('is_active')->orderBy('id')->get();
        $counts = Incident::whereNotNull('category')
            ->selectRaw('category, COUNT(*) as c')
            ->groupBy('category')->pluck('c','category');
        $incidentCount = Incident::count();

        return view('categories.index', compact('categories', 'counts', 'incidentCount'));
    }

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'category_name' => ['required','string','max:32','unique:tbl_categories,category_name'],
            'label'         => ['nullable','string','max:64'],
            'description'   => ['nullable','string','max:500'],
            'color'         => ['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $category = Category::create($data + [
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        AuditLog::record(auth()->id(), 'admin.create_category', 'tbl_categories', $category->id, newValue: [
            'category_name' => $category->category_name,
            'label'         => $category->label,
        ]);
        return redirect()->route('categories.index')->with('success', "Category \"{$category->displayLabel()}\" added.");
    }

    public function categoryUpdate(Request $request, int $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validate([
            'category_name' => ['required','string','max:32', Rule::unique('tbl_categories', 'category_name')->ignore($id)],
            'label'         => ['nullable','string','max:64'],
            'description'   => ['nullable','string','max:500'],
            'color'         => ['nullable','regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $old = $category->only(['category_name','label','description','color']);
        $category->fill($data)->save();
        $category->updated_by = auth()->id();
        $category->save();
        AuditLog::record(auth()->id(), 'admin.update_category', 'tbl_categories', $category->id, oldValue: $old, newValue: $category->only(['category_name','label','description','color']));
        return redirect()->route('categories.index')->with('success', "Category \"{$category->displayLabel()}\" updated.");
    }

    public function categoryDestroy(int $id)
    {
        $category = Category::findOrFail($id);
        $inUse = Incident::where('category', $category->category_name)->count();
        if ($inUse > 0) {
            return redirect()->route('categories.index')
                ->with('error', "Cannot delete \"{$category->displayLabel()}\" — {$inUse} incident(s) use it. Remove it instead to hide it from this screen.");
        }
        $name = $category->displayLabel();
        AuditLog::record(auth()->id(), 'admin.delete_category', 'tbl_categories', $category->id, oldValue: [
            'category_name' => $category->category_name,
            'is_active'     => $category->is_active,
        ]);
        $category->delete();
        return redirect()->route('categories.index')->with('success', "Category \"{$name}\" deleted.");
    }

    public function categoryToggle(int $id)
    {
        $category = Category::findOrFail($id);
        $newStatus = ! $category->is_active;
        $category->is_active  = $newStatus;
        $category->updated_by = auth()->id();
        $category->save();
        AuditLog::record(auth()->id(), $newStatus ? 'admin.restore_category' : 'admin.hide_category', 'tbl_categories', $category->id, oldValue: ['is_active' => ! $newStatus], newValue: ['is_active' => $newStatus]);
        return redirect()->route('categories.index')->with('success', "Category \"{$category->displayLabel()}\" ".($newStatus ? 'restored.' : 'removed from the list.'));
    }

    // ── Assignments (engineer/adjudication view) ─────────────────────

    public function assignments(Request $request)
    {
        $query = \App\Models\Assignment::with([
                'incident:id,description,category,severity,status',
                'teamLeader:id,full_name,department_team',
            ])
            ->orderByDesc('assigned_at');

        // Offsite staff only see their own queue.
        if (auth()->user()->isOffsiteStaff()) {
            $query->where('team_leader_id', auth()->id());
        }

        if ($status = $request->query('status')) {
            $query->where('action_status', $status);
        }

        return view('assignments.index', ['assignments' => $query->paginate(20)]);
    }

    // ── Reports ──────────────────────────────────────────────────────

    public function reports()
    {
        $data = (new ReportAggregator())->dashboard();
        return view('reports.dashboard', ['data' => $data]);
    }

    // ── Settings ─────────────────────────────────────────────────────

    public function settings(Request $request, NlpService $nlp)
    {
        $user = auth()->user();
        $tab = $request->query('tab', 'profile');
        if ($tab === 'system' && ! $user->isAdministrator()) {
            $tab = 'profile';
        }

        $data = [
            'user' => $user,
            'tab'  => $tab,
        ];

        // All panels render in the DOM (CSS toggles visibility), so every
        // tab's data is always loaded.
        $data['notifications'] = Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);
        $data['unreadCount'] = Notification::where('user_id', $user->id)->where('is_read', false)->count();

        // System panel is rendered for admins on every tab, so its data is
        // always loaded for them.
        if ($user->isAdministrator()) {
            $data['nlpHealth']          = $nlp->health();
            $data['nlpSeverityConfig']  = $nlp->severityConfig();
            $data['phpVersion']         = PHP_VERSION;
            $data['laravelVersion']     = app()->version();
            $data['pgVersion']          = DB::selectOne('SHOW server_version')->server_version ?? 'unknown';
            $data['appEnv']             = app()->environment();
            $data['nlpUrl']             = env('NLP_SERVICE_URL', 'http://127.0.0.1:8000');
        }

        return view('settings', $data);
    }

    public function settingsUpdate(Request $request)
    {
        $data = $request->validate([
            'full_name'  => ['required','string','max:120'],
            'contact_no' => ['nullable','string','max:32'],
            'address'    => ['nullable','string','max:500'],
        ]);
        /** @var User $user */
        $user = auth()->user();
        $old = $user->only(['full_name','contact_no','address']);
        $user->fill($data)->save();
        AuditLog::record($user->id, 'self.update_profile', 'users', $user->id, oldValue: $old, newValue: $user->only(['full_name','contact_no','address']));
        return back()->with('success', 'Profile saved.');
    }

    // ── Settings — Notifications ─────────────────────────────────────

    public function markNotificationsRead(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();
        Notification::where('user_id', $user->id)->where('is_read', false)->update(['is_read' => true]);
        return back()->with('success', 'All notifications marked as read.');
    }

    public function notificationToggle(int $id)
    {
        $notif = Notification::where('user_id', auth()->id())->findOrFail($id);
        $notif->is_read = ! $notif->is_read;
        $notif->save();
        return back()->with('success', $notif->is_read ? 'Notification marked as read.' : 'Notification marked as unread.');
    }

    // ── Settings — Security ──────────────────────────────────────────

    public function securityUpdate(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required'],
            'password'         => ['required','string','min:8','confirmed'],
        ]);
        /** @var User $user */
        $user = auth()->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
        }
        $old = ['password_hash' => '***'];
        $user->fill(['password' => $data['password']])->save();
        AuditLog::record($user->id, 'self.change_password', 'users', $user->id, oldValue: $old, newValue: ['password_hash' => '***']);
        return back()->with('success', 'Password updated.');
    }

    // ── Settings — Email ─────────────────────────────────────────────

    public function emailUpdate(Request $request)
    {
        $data = $request->validate([
            'email'            => ['required','email','unique:users,email'],
            'current_password' => ['required'],
        ]);
        /** @var User $user */
        $user = auth()->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])->withInput();
        }
        $old = ['email' => $user->email];
        $user->fill(['email' => $data['email']])->save();
        AuditLog::record($user->id, 'self.change_email', 'users', $user->id, oldValue: $old, newValue: ['email' => $user->email]);
        return back()->with('success', 'Email updated.');
    }

    // ── Settings — System (admin-only) ───────────────────────────────

    public function systemRetrain(Request $request, NlpService $nlp)
    {
        if (!auth()->user()->isAdministrator()) {
            abort(403);
        }
        try {
            $result = $nlp->retrain();
            AuditLog::record(auth()->id(), 'admin.retrain_ai', 'system', 0, newValue: $result);
            return redirect()->route('settings', ['tab' => 'system'])
                ->with('success', $result['message'] ?? 'AI model retraining queued.');
        } catch (\Throwable $e) {
            AuditLog::record(auth()->id(), 'admin.retrain_ai_failed', 'system', 0, newValue: ['error' => $e->getMessage()]);
            return redirect()->route('settings', ['tab' => 'system'])
                ->with('error', 'Retrain request failed: '.$e->getMessage());
        }
    }
}
