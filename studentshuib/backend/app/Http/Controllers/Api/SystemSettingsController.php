<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * System-wide settings — currently just the academic semester window.
 * All routes are super_admin only via the role middleware on /super.
 */
class SystemSettingsController extends Controller
{
    public function __construct(private AuditService $audit) {}

    // GET /api/v1/super/settings
    public function index(): JsonResponse
    {
        return response()->json([
            'semester' => [
                'label'      => AppSetting::get('semester.label', 'Current Semester'),
                'start_date' => AppSetting::get('semester.start_date'),
                'end_date'   => AppSetting::get('semester.end_date'),
            ],
        ]);
    }

    // PUT /api/v1/super/settings
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'semester.label'      => 'required|string|max:50',
            'semester.start_date' => 'required|date',
            'semester.end_date'   => 'required|date|after_or_equal:semester.start_date',
        ]);

        $userId = $request->user()->id;

        AppSetting::set('semester.label',      $data['semester']['label'],      $userId);
        AppSetting::set('semester.start_date', $data['semester']['start_date'], $userId);
        AppSetting::set('semester.end_date',   $data['semester']['end_date'],   $userId);

        $this->audit->log($userId, 'settings.updated', 'AppSetting', 0, null, $data);

        return response()->json([
            'message'  => 'Settings updated.',
            'semester' => [
                'label'      => $data['semester']['label'],
                'start_date' => $data['semester']['start_date'],
                'end_date'   => $data['semester']['end_date'],
            ],
        ]);
    }
}
