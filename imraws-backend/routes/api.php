<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\IncidentController;

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
