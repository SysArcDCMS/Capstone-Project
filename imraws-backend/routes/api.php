<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| IMRAWS-NLP API Routes
|--------------------------------------------------------------------------
|
| All routes are mounted under /api by Laravel's default withRouting.
| Authentication is JWT-based via the `auth:api` middleware, with role
| checks layered via the `role:...` middleware.
|
| Capstone roles:
|   - customer       — files complaints
|   - offsite_staff  — receives assignments
|   - engineer       — reviews flagged incidents, adjudicates
|   - administrator  — manages users, sees all reports
*/

// ── Public auth endpoints ─────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

// ── Authenticated endpoints ───────────────────────────────────────────
Route::middleware('auth:api')->prefix('auth')->group(function () {
    Route::get('me',      [AuthController::class, 'me']);
    Route::patch('profile',[AuthController::class, 'updateProfile']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh',[AuthController::class, 'refresh']);
});

// ── Incidents ─────────────────────────────────────────────────────────
Route::middleware('auth:api')->group(function () {
    Route::get('incidents',               [IncidentController::class, 'index']);
    Route::post('incidents',              [IncidentController::class, 'store']);

    Route::get('incidents/{id}',          [IncidentController::class, 'show'])
        ->whereNumber('id');

    Route::patch('incidents/{id}/status', [IncidentController::class, 'updateStatus'])
        ->whereNumber('id')
        ->middleware('role:offsite_staff,engineer,administrator');

    // Manual re-route (admin/engineer)
    Route::post('incidents/{id}/route',   [AssignmentController::class, 'routeIncident'])
        ->whereNumber('id')
        ->middleware('role:engineer,administrator');

    // Photo proof attachments (DFD 5.6)
    Route::get('incidents/{id}/attachments',  [AttachmentController::class, 'index'])
        ->whereNumber('id');
    Route::post('incidents/{id}/attachments', [AttachmentController::class, 'store'])
        ->whereNumber('id')
        ->middleware('role:offsite_staff,engineer,administrator');
    Route::delete('attachments/{id}',        [AttachmentController::class, 'destroy'])
        ->whereNumber('id');
});

// ── Availability ──────────────────────────────────────────────────────
// Engineers and administrators can read all; offsite_staff can toggle own.
Route::middleware('auth:api')->prefix('availability')->group(function () {
    Route::get('/',  [AvailabilityController::class, 'index']);
    Route::get('/me', [AvailabilityController::class, 'me']);
    Route::post('/', [AvailabilityController::class, 'store']);
});

// ── User Management ───────────────────────────────────────────────────
// Admin CRUD + self profile updates (DFD 1.5, 1.7, 1.8).
Route::middleware('auth:api')->prefix('users')->group(function () {
    Route::get('/',                    [UserController::class, 'index'])
        ->middleware('role:administrator,engineer');
    Route::post('/',                   [UserController::class, 'store'])
        ->middleware('role:administrator');
    Route::get('/{id}',                [UserController::class, 'show'])
        ->whereNumber('id')
        ->middleware('role:administrator,engineer');
    Route::patch('/{id}',              [UserController::class, 'update'])
        ->whereNumber('id');
    Route::patch('/{id}/deactivate',   [UserController::class, 'deactivate'])
        ->whereNumber('id')
        ->middleware('role:administrator');
});

// ── Notifications ─────────────────────────────────────────────────────
Route::middleware('auth:api')->prefix('notifications')->group(function () {
    Route::get('/',                      [NotificationController::class, 'index']);
    Route::patch('/{id}/read',           [NotificationController::class, 'markRead'])->whereNumber('id');
    Route::post('/mark-all-read',        [NotificationController::class, 'markAllRead']);
});

// ── Reports / Analytics (DFD 6.0) ─────────────────────────────────────
Route::middleware('auth:api')->prefix('reports')->group(function () {
    Route::get('/dashboard',    [ReportController::class, 'dashboard']);
    Route::get('/incidents',    [ReportController::class, 'incidents']);
    Route::get('/audit-logs',   [ReportController::class, 'auditLogs'])
        ->middleware('role:administrator,engineer');
});

// ── Assignments ───────────────────────────────────────────────────────
Route::middleware('auth:api')->prefix('assignments')->group(function () {
    Route::get('/',                  [AssignmentController::class, 'index']);
    Route::get('/{id}',              [AssignmentController::class, 'show'])->whereNumber('id');

    // Team leader 3-action endpoint — capstone DFD 4.4 / 4.5 / 4.7
    Route::post('/{id}/team-leader-action', [AssignmentController::class, 'teamLeaderAction'])
        ->whereNumber('id')
        ->middleware('role:offsite_staff');

    // Engineer 3-mode adjudication — capstone DFD 4.10
    Route::post('/{id}/engineer-adjudicate', [AssignmentController::class, 'engineerAdjudicate'])
        ->whereNumber('id')
        ->middleware('role:engineer');
});
