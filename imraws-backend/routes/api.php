<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

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
