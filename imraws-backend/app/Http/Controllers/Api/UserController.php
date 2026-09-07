<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * User management controller — capstone DFD 1.0 (User Management).
 *
 *   1.5 Admin Creates/Updates User Accounts
 *   1.6 Assign/Modify User Roles
 *   1.7 Deactivate/Reactivate User Account
 *   1.8 Update Profile Information  (any role, own profile)
 */
class UserController extends Controller
{
    /**
     * GET /api/users
     * Admin only.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->select('id', 'full_name', 'email', 'role', 'department_team', 'is_active', 'is_team_leader', 'created_at')
            ->orderByDesc('created_at');

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }
        if ($active = $request->query('is_active')) {
            $query->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }
        if ($q = $request->query('q')) {
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'ilike', "%{$q}%")
                  ->orWhere('email', 'ilike', "%{$q}%");
            });
        }

        return response()->json(['data' => $query->paginate((int) $request->query('per_page', 20))]);
    }

    /**
     * POST /api/users
     * Admin creates a user (any role).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'       => ['required', 'string', 'max:120'],
            'email'           => ['required', 'email', 'unique:users,email'],
            'password'        => ['required', 'string', 'min:8'],
            'contact_no'      => ['nullable', 'string', 'max:32'],
            'address'         => ['nullable', 'string', 'max:500'],
            'role'            => ['required', Rule::in([
                User::ROLE_CUSTOMER, User::ROLE_ADMINISTRATOR,
                User::ROLE_ENGINEER, User::ROLE_OFFSITE_STAFF,
            ])],
            'department_team' => ['nullable', 'string', 'max:64'],
            'is_team_leader'  => ['boolean'],
        ]);

        /** @var User $admin */
        $admin = $request->user();

        $user = User::create([
            'full_name'       => $data['full_name'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'contact_no'      => $data['contact_no'] ?? null,
            'address'         => $data['address'] ?? null,
            'role'            => $data['role'],
            'department_team' => $data['department_team'] ?? null,
            'is_team_leader'  => $data['is_team_leader'] ?? false,
            'is_active'       => true,
        ]);

        AuditLog::record(
            userId:    $admin->id,
            action:    'admin.create_user',
            tableName: 'users',
            recordId:  $user->id,
            newValue:  ['role' => $user->role, 'email' => $user->email],
        );

        return response()->json(['data' => $user->only([
            'id', 'full_name', 'email', 'role', 'department_team',
            'is_active', 'is_team_leader', 'created_at',
        ])], 201);
    }

    /**
     * GET /api/users/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }
        return response()->json(['data' => $user]);
    }

    /**
     * PATCH /api/users/{id}
     * Admin can update any field. Non-admins can only update own profile.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $user  = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $isAdmin = $actor->isAdministrator();
        $isSelf  = $actor->id === $user->id;

        if (! $isAdmin && ! $isSelf) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $rules = [
            'full_name'       => ['sometimes', 'string', 'max:120'],
            'contact_no'      => ['sometimes', 'nullable', 'string', 'max:32'],
            'address'         => ['sometimes', 'nullable', 'string', 'max:500'],
            'department_team' => ['sometimes', 'nullable', 'string', 'max:64'],
            'is_team_leader'  => ['sometimes', 'boolean'],
        ];

        if ($isAdmin) {
            $rules['role']       = ['sometimes', Rule::in([
                User::ROLE_CUSTOMER, User::ROLE_ADMINISTRATOR,
                User::ROLE_ENGINEER, User::ROLE_OFFSITE_STAFF,
            ])];
            $rules['is_active']  = ['sometimes', 'boolean'];
            $rules['password']   = ['sometimes', 'string', 'min:8'];
        }

        $data = $request->validate($rules);

        $old = $user->only(['full_name', 'role', 'is_active', 'is_team_leader', 'department_team']);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->fill($data)->save();

        AuditLog::record(
            userId:    $actor->id,
            action:    $isAdmin ? 'admin.update_user' : 'self.update_profile',
            tableName: 'users',
            recordId:  $user->id,
            oldValue:  $old,
            newValue:  $user->only(['full_name', 'role', 'is_active', 'is_team_leader', 'department_team']),
        );

        return response()->json(['data' => $user->fresh()]);
    }

    /**
     * PATCH /api/users/{id}/deactivate
     * DFD 1.7 — toggle is_active.
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $user  = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $newStatus = ! $user->is_active;
        $user->is_active  = $newStatus;
        $user->updated_by = $admin->id;
        $user->save();

        AuditLog::record(
            userId:    $admin->id,
            action:    $newStatus ? 'admin.reactivate_user' : 'admin.deactivate_user',
            tableName: 'users',
            recordId:  $user->id,
            oldValue:  ['is_active' => ! $newStatus],
            newValue:  ['is_active' => $newStatus],
        );

        return response()->json([
            'data' => ['id' => $user->id, 'is_active' => $user->is_active],
            'message' => $newStatus ? 'User reactivated.' : 'User deactivated.',
        ]);
    }
}
