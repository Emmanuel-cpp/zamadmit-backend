<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\ProgrammeController;
use App\Http\Controllers\Api\ApplicationController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\ApplicationController as AdminApplicationController;

/*
|--------------------------------------------------------------------------
| Public routes — no authentication required
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
Route::get('/institutions',        [InstitutionController::class, 'index']);
Route::get('/institutions/{slug}', [InstitutionController::class, 'show']);
Route::get('/programmes',          [ProgrammeController::class, 'index']);
Route::get('/programmes/{slug}',   [ProgrammeController::class, 'show']);
Route::get('/invites/{token}',         [\App\Http\Controllers\Api\InviteController::class, 'show']);
Route::post('/invites/{token}/accept', [\App\Http\Controllers\Api\InviteController::class, 'accept']);

/*
|--------------------------------------------------------------------------
| Authenticated routes — require valid Sanctum token
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Always-allowed routes
    | These can be hit even when must_change_password is true, so the user
    | can complete the password change and continue.
    |----------------------------------------------------------------------
    */
    Route::post('/change-password', [PasswordController::class, 'change']);
    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::get('/user',             [AuthController::class, 'user']);

    /*
    |----------------------------------------------------------------------
    | Routes that require the user to have changed their initial password
    |----------------------------------------------------------------------
    */
    Route::middleware('password.changed')->group(function () {

        // Student profile
        Route::put('/profile',         [ProfileController::class, 'update']);
        Route::post('/profile/grades', [ProfileController::class, 'saveGrades']);

        // Documents
        Route::get('/documents',         [DocumentController::class, 'index']);
        Route::post('/documents',        [DocumentController::class, 'store']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

        // Applications (student)
        Route::get('/applications',      [ApplicationController::class, 'index']);
        Route::post('/applications',     [ApplicationController::class, 'store']);
        Route::get('/applications/{id}', [ApplicationController::class, 'show']);

        // AI-powered recommendations
        Route::get('/recommendations', [\App\Http\Controllers\Api\RecommendationController::class, 'index']);

        // Notifications
        Route::get('/notifications',            [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all',  [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

        // Payments (simulated mobile money)
        Route::get('/payments',               [\App\Http\Controllers\Api\PaymentController::class, 'index']);
        Route::post('/payments',              [\App\Http\Controllers\Api\PaymentController::class, 'initiate']);
        Route::post('/payments/{id}/confirm', [\App\Http\Controllers\Api\PaymentController::class, 'confirm']);

        /*
        |------------------------------------------------------------------
        | Institution admin routes
        |------------------------------------------------------------------
        */
Route::middleware('role:institution_admin')->prefix('admin')->group(function () {

            // ── Viewing (all admin tiers) ─────────────────────────────
            Route::middleware('admin.can:view')->group(function () {
                Route::get('/applications',        [AdminApplicationController::class, 'index']);
                Route::get('/applications/export', [AdminApplicationController::class, 'export']);
                Route::get('/applications/{id}',   [AdminApplicationController::class, 'show']);
                Route::get('/analytics',           [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'index']);
                Route::get('/programmes',          [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'index']);
                Route::get('/programmes/{id}',     [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'show']);
                
            });

            // ── Deciding (owner + admissions_officer) ─────────────────
            Route::middleware('admin.can:decide')->group(function () {
                Route::put('/applications/{id}', [AdminApplicationController::class, 'update']);
            });

            // ── Programme management (owner only) ─────────────────────
            Route::middleware('admin.can:manage_programmes')->group(function () {
                Route::post('/programmes',     [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'store']);
                Route::put('/programmes/{id}', [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'update']);
                Route::get('/audit-logs', [\App\Http\Controllers\Api\Admin\AuditLogController::class, 'index']);
            });

            // ── Institution settings (owner only) ─────────────────────
            Route::middleware('admin.can:manage_settings')->group(function () {
                Route::put('/institution', [\App\Http\Controllers\Api\Admin\InstitutionController::class, 'update']);
                Route::get('/institution',         [\App\Http\Controllers\Api\Admin\InstitutionController::class, 'show']);
            });

            // ── Team management (owner only) ──────────────────────────
            Route::middleware('admin.can:manage_admins')->group(function () {
                Route::get('/team',                 [\App\Http\Controllers\Api\Admin\TeamController::class, 'index']);
                Route::get('/audit-logs', [\App\Http\Controllers\Api\Admin\AuditLogController::class, 'index']);
                Route::post('/team/invites',        [\App\Http\Controllers\Api\Admin\TeamController::class, 'invite']);
                Route::delete('/team/invites/{id}', [\App\Http\Controllers\Api\Admin\TeamController::class, 'revokeInvite']);
                Route::put('/team/{userId}',        [\App\Http\Controllers\Api\Admin\TeamController::class, 'updateRole']);
                Route::delete('/team/{userId}',     [\App\Http\Controllers\Api\Admin\TeamController::class, 'remove']);
            });
        });
    });
});