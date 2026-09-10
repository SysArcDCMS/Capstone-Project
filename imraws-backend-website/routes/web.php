<?php

use App\Http\Controllers\Web\PortalController;
use Illuminate\Support\Facades\Route;

// ── Guest routes ──────────────────────────────────────────────────────
Route::middleware('guest:web')->group(function () {
    Route::get('/login', [PortalController::class, 'showLogin'])->name('login');
    Route::post('/login', [PortalController::class, 'login'])->name('login.attempt');
});

// ── Authenticated web portal ─────────────────────────────────────────
// Engineers + Administrators (Offsite Staff may also browse).
Route::middleware('auth:web')->group(function () {
    Route::post('/logout', [PortalController::class, 'logout'])->name('logout');

    Route::get('/',           [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard',  [PortalController::class, 'dashboard'])->name('dashboard.alt');

    // Complaints
    Route::get('/complaints',                  [PortalController::class, 'complaints'])->name('complaints.index');
    Route::get('/complaints/export',           [PortalController::class, 'complaintsExport'])->name('complaints.export');
    Route::get('/complaints/{id}',             [PortalController::class, 'complaintShow'])->whereNumber('id')->name('complaints.show');

    // Users
    Route::get('/users',                  [PortalController::class, 'users'])->name('users.index');
    Route::get('/users/create',           [PortalController::class, 'userCreate'])->name('users.create');
    Route::post('/users',                 [PortalController::class, 'userStore'])->name('users.store');
    Route::get('/users/{id}/edit',        [PortalController::class, 'userEdit'])->whereNumber('id')->name('users.edit');
    Route::put('/users/{id}',             [PortalController::class, 'userUpdate'])->whereNumber('id')->name('users.update');
    Route::patch('/users/{id}/deactivate',[PortalController::class, 'userDeactivate'])->whereNumber('id')->name('users.deactivate');

    // Categories
    Route::get('/categories', [PortalController::class, 'categories'])->name('categories.index');

    // Assignments (placeholder - full view in Phase 9 follow-up)
    Route::get('/assignments', [PortalController::class, 'assignments'])->name('assignments.index');

    // Reports
    Route::get('/reports', [PortalController::class, 'reports'])->name('reports.dashboard');

    // Settings (profile)
    Route::get('/settings',           [PortalController::class, 'settings'])->name('settings');
    Route::patch('/settings',         [PortalController::class, 'settingsUpdate'])->name('settings.profile');
});
