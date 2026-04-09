<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Student\SubmissionController as StudentSubmissionController;
use App\Http\Controllers\Student\NotificationController as StudentNotificationController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FormTypeController as AdminFormTypeController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\FormTypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — DIU Student Services
|--------------------------------------------------------------------------
| All responses are JSON. Authentication via Laravel Sanctum (API tokens).
| Rate limiting applied to auth endpoints to prevent brute force.
*/

// ── Public: Auth ─────────────────────────────────────────────────────────
Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);  // student self-registration
});

// ── Authenticated routes ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    // ── STUDENT routes ────────────────────────────────────────────────────
    Route::middleware('role:student')->prefix('student')->group(function () {

        // Form types — what can I submit?
        Route::get('form-types',          [FormTypeController::class, 'indexForStudent']);
        Route::get('form-types/{slug}',   [FormTypeController::class, 'show']);

        // Submissions
        Route::get    ('submissions',          [StudentSubmissionController::class, 'index']);
        Route::post   ('submissions',          [StudentSubmissionController::class, 'store']);
        Route::get    ('submissions/{ref}',    [StudentSubmissionController::class, 'show']);
        Route::put    ('submissions/{ref}',    [StudentSubmissionController::class, 'update']);   // resubmit after return
        Route::delete ('submissions/{ref}',    [StudentSubmissionController::class, 'cancel']);   // cancel draft

        // Documents
        Route::post('submissions/{ref}/documents', [StudentSubmissionController::class, 'uploadDocument']);

        // Comments
        Route::get ('submissions/{ref}/comments', [StudentSubmissionController::class, 'comments']);
        Route::post('submissions/{ref}/comments', [StudentSubmissionController::class, 'addComment']);

        // Notifications
        Route::get ('notifications',       [StudentNotificationController::class, 'index']);
        Route::post('notifications/read',  [StudentNotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [StudentNotificationController::class, 'markRead']);
    });

    // ── ADMIN routes ──────────────────────────────────────────────────────
    Route::middleware('role:admin,super_admin')->prefix('admin')->group(function () {

        // Dashboard stats
        Route::get('dashboard',       [DashboardController::class, 'index']);
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);

        // Submission queue
        Route::get('submissions',          [AdminSubmissionController::class, 'index']);
        Route::get('submissions/{ref}',    [AdminSubmissionController::class, 'show']);

        // Status transitions — the core action
        Route::post('submissions/{ref}/approve',  [AdminSubmissionController::class, 'approve']);
        Route::post('submissions/{ref}/reject',   [AdminSubmissionController::class, 'reject']);
        Route::post('submissions/{ref}/return',   [AdminSubmissionController::class, 'returnToStudent']);
        Route::post('submissions/{ref}/escalate', [AdminSubmissionController::class, 'escalate']);
        Route::post('submissions/{ref}/assign',   [AdminSubmissionController::class, 'assign']);
        Route::post('submissions/{ref}/review',   [AdminSubmissionController::class, 'markInReview']);

        // Admin comments (internal + public)
        Route::post('submissions/{ref}/comments',          [AdminSubmissionController::class, 'addComment']);
        Route::post('submissions/{ref}/internal-notes',    [AdminSubmissionController::class, 'updateInternalNotes']);

        // Documents
        Route::post('submissions/{ref}/documents',         [AdminSubmissionController::class, 'uploadDocument']);
        Route::post('submissions/{ref}/generate-document', [AdminSubmissionController::class, 'generateDocument']);

        // Bulk actions
        Route::post('submissions/bulk',   [AdminSubmissionController::class, 'bulk']);

        // ── Super admin only ──────────────────────────────────────────────
        Route::middleware('role:super_admin')->group(function () {

            // Form types: full CRUD + field management
            Route::get   ('form-types',                                    [FormTypeController::class, 'index']);
            Route::post  ('form-types',                                    [FormTypeController::class, 'store']);
            Route::get   ('form-types/{formType}',                         [FormTypeController::class, 'show']);
            Route::put   ('form-types/{formType}',                         [FormTypeController::class, 'update']);
            Route::delete('form-types/{formType}',                         [FormTypeController::class, 'destroy']);
            Route::post  ('form-types/{formType}/toggle',                  [FormTypeController::class, 'toggleActive']);
            Route::get   ('form-types/{formType}/fields',                  [FormTypeController::class, 'fieldsIndex']);
            Route::post  ('form-types/{formType}/fields',                  [FormTypeController::class, 'fieldsStore']);
            Route::put   ('form-types/{formType}/fields/{field}',          [FormTypeController::class, 'fieldsUpdate']);
            Route::delete('form-types/{formType}/fields/{field}',          [FormTypeController::class, 'fieldsDestroy']);
            Route::post  ('form-types/{formType}/fields/reorder',          [FormTypeController::class, 'fieldsReorder']);

            // Departments: CRUD + signatory + escalation rules
            Route::get   ('departments',                                           [DepartmentController::class, 'index']);
            Route::post  ('departments',                                           [DepartmentController::class, 'store']);
            Route::get   ('departments/{department}',                              [DepartmentController::class, 'show']);
            Route::put   ('departments/{department}',                              [DepartmentController::class, 'update']);
            Route::delete('departments/{department}',                              [DepartmentController::class, 'destroy']);
            Route::post  ('departments/{department}/toggle',                       [DepartmentController::class, 'toggleActive']);
            Route::post  ('departments/{department}/signatory-logo',               [DepartmentController::class, 'uploadSignatoryLogo']);
            Route::delete('departments/{department}/signatory-logo',               [DepartmentController::class, 'deleteSignatoryLogo']);

            // Branding / portal settings
            Route::get   ('settings/branding',        [BrandingController::class, 'index']);
            Route::put   ('settings/branding',        [BrandingController::class, 'update']);
            Route::post  ('settings/branding/logo',   [BrandingController::class, 'uploadLogo']);
            Route::delete('settings/branding/logo',   [BrandingController::class, 'deleteLogo']);

            // Users: list, create, update, toggle, role change, password reset, delete
            Route::get   ('users',                          [UserController::class, 'index']);
            Route::post  ('users',                          [UserController::class, 'store']);
            Route::get   ('users/me',                       [UserController::class, 'me']);
            Route::put   ('users/me',                       [UserController::class, 'updateMe']);
            Route::get   ('users/{user}',                   [UserController::class, 'show']);
            Route::put   ('users/{user}',                   [UserController::class, 'update']);
            Route::delete('users/{user}',                   [UserController::class, 'destroy']);
            Route::post  ('users/{user}/toggle',            [UserController::class, 'toggleActive']);
            Route::post  ('users/{user}/role',              [UserController::class, 'changeRole']);
            Route::post  ('users/{user}/reset-password',    [UserController::class, 'resetPassword']);

            // Audit log
            Route::get('audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index']);
        });

        // Admin notifications
        Route::get ('notifications',           [StudentNotificationController::class, 'index']);
        Route::post('notifications/read',      [StudentNotificationController::class, 'markAllRead']);
    });
});
