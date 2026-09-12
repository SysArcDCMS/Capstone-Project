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

        if (! Auth::guard('web')->attempt($credentials)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
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
