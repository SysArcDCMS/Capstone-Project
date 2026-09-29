<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ResetCodeMail;
use App\Models\AuditLog;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

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
    /** How long an emailed reset code stays valid. */
    private const RESET_CODE_TTL_MINUTES = 10;

    /** Failed guesses before the code is destroyed and must be re-requested. */
    private const MAX_RESET_ATTEMPTS = 5;

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
     *
     * Enforces the lockout rule: after 3 consecutive failed attempts the
     * account is locked for 15 minutes (423 response). Counters reset on
     * a successful login.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->isLocked()) {
            return response()->json([
                'message' => 'Account locked due to too many failed attempts. '
                    ."Try again in {$user->locked_until->diffInMinutes(now())} minute(s).",
            ], 423);
        }

        $token = Auth::guard('api')->attempt($credentials);

        if (! $token) {
            $user?->registerFailedLogin();

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

        $user->resetLoginAttempts();

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

        // A text field the user emptied arrives as '', but both columns are
        // nullable, so store null instead. Otherwise the row ends up holding
        // an empty string that reads back as "set but blank", and anything
        // testing for null misses it.
        foreach (['contact_no', 'address'] as $optional) {
            if (array_key_exists($optional, $data) && trim((string) $data[$optional]) === '') {
                $data[$optional] = null;
            }
        }

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
     * POST /api/auth/change-password
     *
     * Any authenticated user may rotate their own password. The current
     * password is verified first; the new one must be at least 8 chars.
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors'  => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        AuditLog::record(
            userId:    $user->id,
            action:    'self.change_password',
            tableName: 'users',
            recordId:  $user->id,
        );

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
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
     *
     * Public on purpose. An expired JWT cannot satisfy the `auth:api`
     * middleware, so a refresh route placed behind it could never do its
     * only job — reviving a dead session.
     *
     * tymon validates the presented token against `jwt.refresh_ttl` (14 days
     * by default) in refresh flow, so a merely-expired token is renewed while
     * a malformed, revoked or long-dead one is rejected. `refresh(true)`
     * blacklists the spent token permanently, so every refresh rotates.
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $request->input('token') ?: $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'No token supplied.'], 401);
        }

        $guard = Auth::guard('api');

        try {
            $fresh = $guard->setToken($token)->refresh(true);
        } catch (TokenExpiredException) {
            return response()->json([
                'message' => 'Session expired. Please log in again.',
            ], 401);
        } catch (JWTException) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        return response()->json([
            'access_token' => $fresh,
            'token_type'   => 'bearer',
            'expires_in'   => $guard->factory()->getTTL() * 60,
        ]);
    }

    /**
     * POST /api/auth/forgot-password
     *
     * Emails a 6-digit reset code. The response is identical whether or not
     * the address is registered — a different answer for an unknown address
     * would let anyone enumerate who holds an account.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);

        $email = $this->normaliseEmail($data['email']);

        if ($this->findUserByEmail($email)) {
            // Only one code may be live per address, so asking for a new one
            // retires the old.
            PasswordResetCode::where('email', $email)->delete();

            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordResetCode::create([
                'email'      => $email,
                'code_hash'  => Hash::make($code),
                'expires_at' => now()->addMinutes(self::RESET_CODE_TTL_MINUTES),
            ]);

            Mail::to($email)->send(
                new ResetCodeMail($code, self::RESET_CODE_TTL_MINUTES)
            );
        }

        return response()->json([
            'message'    => 'If that email is registered, a reset code is on its way.',
            'expires_in' => self::RESET_CODE_TTL_MINUTES * 60,
        ], 202);
    }

    /**
     * POST /api/auth/reset-password
     *
     * Consumes a valid code and sets a new password. Codes are stored only as
     * bcrypt hashes, expire after 10 minutes, and are destroyed after 5 wrong
     * guesses.
     *
     * Note: JWTs are stateless, so a token minted before this call stays valid
     * until it expires (JWT_TTL, currently 24h). Revoking it here would need a
     * per-token blacklist, which is beyond this flow.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'string', 'email:rfc', 'max:255'],
            'code'     => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $this->normaliseEmail($data['email']);

        $user = $this->findUserByEmail($email);
        $record = PasswordResetCode::where('email', $email)
            ->orderByDesc('created_at')
            ->first();

        $invalid = static fn () => response()->json([
            'message' => 'That reset code is invalid or has expired.',
            'errors'  => ['code' => ['That reset code is invalid or has expired.']],
        ], 422);

        if (! $user || ! $record) {
            return $invalid();
        }

        if ($record->attempts >= self::MAX_RESET_ATTEMPTS) {
            $record->delete();

            return response()->json([
                'message' => 'Too many attempts. Please request a new code.',
            ], 429);
        }

        if (! $record->isUsable()) {
            $record->delete();

            return $invalid();
        }

        if (! Hash::check($data['code'], $record->code_hash)) {
            $record->increment('attempts');

            // The budget is spent, so say so rather than replying as if it
            // were merely a wrong guess — the client needs to prompt for a
            // fresh code.
            if ($record->attempts >= self::MAX_RESET_ATTEMPTS) {
                $record->delete();

                return response()->json([
                    'message' => 'Too many attempts. Please request a new code.',
                ], 429);
            }

            return $invalid();
        }

        $user->forceFill([
            'password'   => Hash::make($data['password']),
            'updated_at' => now(),
        ])->save();

        $record->delete();
        PasswordResetCode::where('email', $email)->delete();

        AuditLog::record($user->id, 'auth.password_reset', 'users', $user->id);

        return response()->json([
            'message' => 'Password updated. You can now sign in.',
        ]);
    }

    /** Addresses are compared case-insensitively throughout. */
    private function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Look up an account by address, ignoring case.
     *
     * Postgres compares text case-sensitively, so `where('email', $email)`
     * misses accounts that were registered as `Resident@Example.com`. Lowering
     * both sides also keeps the stored password_reset_codes.email consistent.
     */
    private function findUserByEmail(string $email): ?User
    {
        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
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
                // Must match the shape returned by `me()` and by
                // updateProfile(). These were omitted here, so after a login
                // the app's cached User had contactNo/address == null. The
                // account screen seeds its text fields from that object, and
                // saving any unrelated field then submitted the blanks back
                // and silently erased the real values. The response shape has
                // to be identical across all three, or the client cannot tell
                // "empty" from "never loaded".
                'contact_no'      => $user->contact_no,
                'address'         => $user->address,
                'role'            => $user->role,
                'is_active'       => $user->is_active,
                'is_team_leader'  => $user->is_team_leader,
                'department_team' => $user->department_team,
                'created_at'      => $user->created_at,
            ],
        ], $status);
    }
}
