<?php

use Illuminate\Support\Facades\Route;
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
// Any authenticated user can list/show incidents (filtered by role
// inside the controller). Status updates are limited to offsite_staff
// and engineer per capstone DFD 5.3 / 5.7. Customer-only creation
// for /store is enforced inside the controller.
Route::middleware('auth:api')->group(function () {
    Route::get('incidents',               [IncidentController::class, 'index']);
    Route::post('incidents',              [IncidentController::class, 'store']);

    Route::get('incidents/{id}',          [IncidentController::class, 'show'])
        ->whereNumber('id');

    Route::patch('incidents/{id}/status', [IncidentController::class, 'updateStatus'])
        ->whereNumber('id')
        ->middleware('role:offsite_staff,engineer,administrator');
});
