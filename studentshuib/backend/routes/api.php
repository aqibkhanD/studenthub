<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\FormTypeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\AdminSubmissionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\FormFieldController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SystemSettingsController;

/*
|--------------------------------------------------------------------------
| StudentsHub API Routes  (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

// ----------------------------------------------------------
// Public routes (no auth required)
// ----------------------------------------------------------
Route::prefix('v1')->group(function () {

    // Auth
    Route::post('auth/login',           [AuthController::class, 'login']);
    Route::post('auth/register',        [AuthController::class, 'register']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('auth/reset-password',  [AuthController::class, 'resetPassword']);

    // Health check
    Route::get('health', fn () => response()->json(['status' => 'ok', 'version' => '1.0.0']));

    // ----------------------------------------------------------
    // Authenticated routes
    // ----------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout',  [AuthController::class, 'logout']);
        Route::get('auth/me',       [AuthController::class, 'me']);
        Route::put('auth/profile',  [AuthController::class, 'updateProfile']);
        Route::put('auth/password', [AuthController::class, 'changePassword']);

        // ----------------------------------------------------------
        // Student routes
        // ----------------------------------------------------------
        Route::middleware('role:student')->prefix('student')->group(function () {

            // Form catalogue — what can they submit
            Route::get('form-types',          [FormTypeController::class, 'indexForStudent']);
            Route::get('form-types/{slug}',   [FormTypeController::class, 'showForStudent']);

            // Submissions
            Route::get('submissions',         [SubmissionController::class, 'index']);
            Route::post('submissions',        [SubmissionController::class, 'store']);
            Route::get('submissions/{ref}',   [SubmissionController::class, 'show']);
            Route::put('submissions/{ref}',   [SubmissionController::class, 'update']);
            Route::delete('submissions/{ref}',[SubmissionController::class, 'cancel']);

            // Documents — student upload (draft, returned, action_required only)
            Route::post('submissions/{ref}/documents', [SubmissionController::class, 'uploadDocument']);

            // Comments on own submission
            Route::get('submissions/{ref}/comments',  [SubmissionController::class, 'comments']);
            Route::post('submissions/{ref}/comments', [SubmissionController::class, 'addComment']);

            // Notifications — IMPORTANT: read-all MUST be defined before {id}/read
            // to prevent "read-all" being captured as an {id} parameter.
            Route::put('notifications/read-all',     [NotificationController::class, 'markAllRead']);
            Route::put('notifications/{id}/read',    [NotificationController::class, 'markRead']);
            Route::get('notifications',              [NotificationController::class, 'index']);
        });

        // ----------------------------------------------------------
        // Admin routes (admin, dept_head, super_admin, management)
        // ----------------------------------------------------------
        Route::middleware('role:admin,dept_head,super_admin,management')->prefix('admin')->group(function () {

            // Dashboard summary
            Route::get('dashboard',               [DashboardController::class, 'index']);

            // Submissions management
            // IMPORTANT: specific paths (bulk-status, export) MUST come before {ref} param routes
            Route::get('submissions',                    [AdminSubmissionController::class, 'index']);
            Route::post('submissions/bulk-status',       [AdminSubmissionController::class, 'bulkStatus']);
            Route::get('submissions/export',             [AdminSubmissionController::class, 'export']);
            Route::get('submissions/{ref}',              [AdminSubmissionController::class, 'show']);
            Route::put('submissions/{ref}/status',[AdminSubmissionController::class, 'updateStatus']);
            Route::put('submissions/{ref}/assign',[AdminSubmissionController::class, 'assign']);
            Route::post('submissions/{ref}/comments', [AdminSubmissionController::class, 'addComment']);
            Route::post('submissions/{ref}/documents',[AdminSubmissionController::class, 'uploadDocument']);

            // Notifications — read-all before {id}/read (same ordering rule)
            Route::put('notifications/read-all',     [NotificationController::class, 'markAllRead']);
            Route::put('notifications/{id}/read',    [NotificationController::class, 'markRead']);
            Route::get('notifications',              [NotificationController::class, 'index']);

            // Staff list (for assignment dropdown)
            Route::get('staff',                   [UserController::class, 'staffList']);

            // Departments (read for all admins)
            Route::get('departments',             [DepartmentController::class, 'index']);
            Route::get('departments/{id}',        [DepartmentController::class, 'show']);

            // Reports — PDF exports accessible to all admin roles
            Route::get('reports/analytics',       [ReportController::class, 'analytics']);
        });

        // ----------------------------------------------------------
        // Super admin routes
        // ----------------------------------------------------------
        Route::middleware('role:super_admin')->prefix('super')->group(function () {

            // User management
            Route::apiResource('users', UserController::class);
            Route::put('users/{id}/toggle-active', [UserController::class, 'toggleActive']);

            // Department management
            Route::apiResource('departments', DepartmentController::class);

            // Form type management
            Route::apiResource('form-types', FormTypeController::class);
            Route::put('form-types/{id}/toggle-active', [FormTypeController::class, 'toggleActive']);

            // Form field management (nested under form types)
            // IMPORTANT: reorder MUST come before {fid} to prevent "reorder" being treated as an ID
            Route::get('form-types/{id}/fields',                [FormFieldController::class, 'index']);
            Route::post('form-types/{id}/fields/reorder',       [FormFieldController::class, 'reorder']);
            Route::post('form-types/{id}/fields',               [FormFieldController::class, 'store']);
            Route::put('form-types/{id}/fields/{fid}',          [FormFieldController::class, 'update']);
            Route::delete('form-types/{id}/fields/{fid}',       [FormFieldController::class, 'destroy']);

            // Audit logs
            Route::get('audit-logs',     [AuditLogController::class, 'index']);
            Route::get('audit-logs/{id}',[AuditLogController::class, 'show']);

            // System settings (semester window etc.)
            Route::get('settings', [SystemSettingsController::class, 'index']);
            Route::put('settings', [SystemSettingsController::class, 'update']);

            // Analytics
            Route::get('analytics/overview',    [DashboardController::class, 'analyticsOverview']);
            Route::get('analytics/sla',         [DashboardController::class, 'slaReport']);
            Route::get('analytics/departments', [DashboardController::class, 'departmentReport']);
        });
    });
});
