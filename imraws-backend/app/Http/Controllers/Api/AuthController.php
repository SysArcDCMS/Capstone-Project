<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Authentication controller — capstone DFD 1.1 to 1.4.
 *
 *   1.1 Customer self-registers via /api/auth/register
 *   1.2 Anyone logs in via /api/auth/login (returns JWT)
 *   1.3 Validate Credentials and JWT Token
 *   1.4 Enforce Role-Based Access Control (in middleware)
 *
 * Customers may self-register; engineers/offsite_staff/administrators
 * are seeded by the administrator in the web portal.
 */
class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     *
     * Self-registration for the customer role only. Returns JWT token.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'       => ['required', 'string', 'max:120'],
            'email'           => ['required', 'email', 'unique:users,email'],
            'password'        => ['required', 'string', 'min:8'],
            'contact_no'      => ['nullable', 'string', 'max:32'],
            'address'         => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::create([
            'full_name'  => $data['full_name'],
            'email'      => $data['email'],
            'password'   => Hash::make($data['password']),
            'contact_no' => $data['contact_no'] ?? null,
            'address'    => $data['address'] ?? null,
            'role'       => User::ROLE_CUSTOMER,
            'is_active'  => true,
        ]);

        AuditLog::record(
            userId:    $user->id,
            action:    'register',
            tableName: 'users',
            recordId:  $user->id,
            newValue:  ['role' => $user->role, 'email' => $user->email],
        );

        $token = Auth::guard('api')->login($user);

        return $this->tokenResponse($token, $user, 201);
    }

    /**
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $token = Auth::guard('api')->attempt($credentials);

        if (! $token) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::guard('api')->user();

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Account is deactivated. Contact your administrator.',
            ], 403);
        }

        AuditLog::record(
            userId:    $user->id,
            action:    'login',
            tableName: 'users',
            recordId:  $user->id,
        );

        return $this->tokenResponse($token, $user);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'id'              => $user->id,
                'full_name'       => $user->full_name,
                'email'           => $user->email,
                'contact_no'      => $user->contact_no,
                'address'         => $user->address,
                'role'            => $user->role,
                'department_team' => $user->department_team,
                'is_active'       => $user->is_active,
                'is_team_leader'  => $user->is_team_leader,
                'created_at'      => $user->created_at,
            ],
        ]);
    }

    /**
     * PATCH /api/auth/profile
     * DFD 1.8 — Update Profile Information (any role, own profile only).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'full_name'  => ['sometimes', 'string', 'max:120'],
            'contact_no' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address'    => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $old = $user->only(['full_name', 'contact_no', 'address']);
        $user->fill($data)->save();

        AuditLog::record(
            userId:    $user->id,
            action:    'self.update_profile',
            tableName: 'users',
            recordId:  $user->id,
            oldValue:  $old,
            newValue:  $user->only(['full_name', 'contact_no', 'address']),
        );

        return response()->json(['data' => $user->fresh()]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        AuditLog::record(
            userId:    $user?->id,
            action:    'logout',
            tableName: 'users',
            recordId:  $user?->id,
        );

        Auth::guard('api')->logout();

        return response()->json([
            'message' => 'Signed out successfully.',
        ]);
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh(): JsonResponse
    {
        $token = Auth::guard('api')->refresh();

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
        ]);
    }

    private function tokenResponse(string $token, User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            'user' => [
                'id'              => $user->id,
                'full_name'       => $user->full_name,
                'email'           => $user->email,
                'role'            => $user->role,
                'is_team_leader'  => $user->is_team_leader,
                'department_team' => $user->department_team,
            ],
        ], $status);
    }
}
