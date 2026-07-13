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

        // Applications
        Route::get('/applications',      [ApplicationController::class, 'index']);
        Route::post('/applications',     [ApplicationController::class, 'store']);
        Route::get('/applications/{id}', [ApplicationController::class, 'show']);
        Route::get('/recommendations', [\App\Http\Controllers\Api\RecommendationController::class, 'index']);// AI-powered recommendations
        
        // Notifications
        Route::get('/notifications',                  [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read',       [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all',        [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

        /*
        |------------------------------------------------------------------
        | Institution admin routes
        |------------------------------------------------------------------
        */
        Route::middleware('role:institution_admin')->prefix('admin')->group(function () {
            Route::get('/applications',      [AdminApplicationController::class, 'index']);
            Route::get('/applications/{id}', [AdminApplicationController::class, 'show']);
            Route::put('/applications/{id}', [AdminApplicationController::class, 'update']);
            Route::get('/analytics', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'index']);

            // Programme management
            Route::get('/programmes',         [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'index']);
            Route::get('/programmes/{id}',    [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'show']);
            Route::post('/programmes',        [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'store']);
            Route::put('/programmes/{id}',    [\App\Http\Controllers\Api\Admin\ProgrammeController::class, 'update']);

            // Institution settings
            Route::get('/institution',  [\App\Http\Controllers\Api\Admin\InstitutionController::class, 'show']);
            Route::put('/institution',  [\App\Http\Controllers\Api\Admin\InstitutionController::class, 'update']);
        });
    });
});