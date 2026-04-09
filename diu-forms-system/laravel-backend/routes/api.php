<?php

use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionDocumentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AdminManagementController;
use App\Http\Controllers\FormTypeController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DIU Student Services — API Routes
|--------------------------------------------------------------------------
| All routes are prefixed /api (set in bootstrap/app.php or RouteServiceProvider).
| Auth: Laravel Sanctum tokens.  Role: custom RoleMiddleware.
|
| Guard config in config/auth.php:
|   'guards' => [
|       'sanctum' => ['driver' => 'sanctum'],
|   ]
| Sanctum resolves the model from the token; students use App\Models\User,
| admins use App\Models\Admin (set via statefulDomains or token abilities).
*/

// ── Public ────────────────────────────────────────────────────────
Route::post('/student/login', [StudentAuthController::class, 'login']);
Route::post('/admin/login',   [AdminAuthController::class,  'login']);

// ── Student ───────────────────────────────────────────────────────
Route::prefix('student')
     ->middleware(['auth:sanctum', 'role:student'])
     ->group(function () {

    // Auth
    Route::post('/logout', [StudentAuthController::class, 'logout']);
    Route::get('/me',      [StudentAuthController::class, 'me']);
    Route::patch('/me/notifications', [StudentAuthController::class, 'updateNotifPrefs']);

    // Form catalogue
    Route::get('/form-types',       [FormTypeController::class, 'publicIndex']);
    Route::get('/form-types/{slug}', [FormTypeController::class, 'publicShow']);

    // Submissions
    Route::get('/submissions',          [SubmissionController::class, 'index']);
    Route::post('/submissions',         [SubmissionController::class, 'store']);
    Route::get('/submissions/{ref}',    [SubmissionController::class, 'show']);
    Route::patch('/submissions/{ref}',  [SubmissionController::class, 'update']);

    // Documents (upload)
    Route::post('/submissions/{ref}/documents', [SubmissionDocumentController::class, 'store']);
    Route::get('/documents/{id}/download',      [SubmissionDocumentController::class, 'download']);

    // In-app notifications
    Route::get('/notifications',              [NotificationController::class, 'studentIndex']);
    Route::post('/notifications/{id}/read',   [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all',    [NotificationController::class, 'markAllRead']);

    // Unsubscribe (token-based, also works unauthenticated — handled separately below)
    Route::get('/unsubscribe', [NotificationController::class, 'unsubscribePage']);
});

// Token-based unsubscribe (linked from emails — no auth required)
Route::get('/unsubscribe', [NotificationController::class, 'handleUnsubscribe']);

// ── Admin ─────────────────────────────────────────────────────────
Route::prefix('admin')
     ->middleware(['auth:sanctum', 'role:admin,super_admin'])
     ->group(function () {

    // Auth
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me',      [AdminAuthController::class, 'me']);
    Route::patch('/me/notifications', [AdminAuthController::class, 'updateNotifPrefs']);

    // Submissions
    Route::get('/submissions',                       [SubmissionController::class, 'adminIndex']);
    Route::get('/submissions/{ref}',                 [SubmissionController::class, 'adminShow']);
    Route::patch('/submissions/{ref}/status',        [SubmissionController::class, 'updateStatus']);
    Route::post('/submissions/{ref}/comment',        [SubmissionController::class, 'addComment']);
    Route::get('/submissions/{ref}/documents',       [SubmissionDocumentController::class, 'adminList']);
    Route::get('/documents/{id}/download',           [SubmissionDocumentController::class, 'adminDownload']);

    // Notifications
    Route::get('/notifications',            [NotificationController::class, 'adminIndex']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all',  [NotificationController::class, 'markAllRead']);

    // Audit log
    Route::get('/audit-log', [AuditLogController::class, 'index']);
    Route::get('/audit-log/{id}', [AuditLogController::class, 'show']);
    Route::get('/audit-log/export', [AuditLogController::class, 'export']);

    // Dashboard stats
    Route::get('/dashboard/stats', [AdminManagementController::class, 'stats']);

    // Super-admin only
    Route::middleware('role:super_admin')->group(function () {
        Route::apiResource('/form-types',   FormTypeController::class);
        Route::apiResource('/departments',  \App\Http\Controllers\DepartmentController::class);
        Route::get('/users',                [AdminManagementController::class, 'listAdmins']);
        Route::post('/users',               [AdminManagementController::class, 'createAdmin']);
        Route::patch('/users/{id}',         [AdminManagementController::class, 'updateAdmin']);
        Route::patch('/users/{id}/toggle',  [AdminManagementController::class, 'toggleAdmin']);
        Route::post('/users/{id}/reset-pw', [AdminManagementController::class, 'resetPassword']);
    });
});
