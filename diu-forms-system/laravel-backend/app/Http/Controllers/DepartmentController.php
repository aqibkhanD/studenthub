<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Department::withCount(['admins', 'formTypes', 'submissions'])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:120',
            'code'      => 'required|string|max:30|unique:departments,code',
            'email'     => 'nullable|email',
            'head_name' => 'nullable|string|max:120',
            'sla_hours' => 'required|integer|min:1|max:720',
        ]);

        $dept = Department::create($validated);

        AuditLog::record($request->user(), 'setting_change', "Created department \"{$dept->name}\"");

        return response()->json($dept, 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(
            Department::withCount(['admins', 'formTypes', 'submissions'])->findOrFail($id)
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:120',
            'code'      => "sometimes|string|max:30|unique:departments,code,{$id}",
            'email'     => 'sometimes|nullable|email',
            'head_name' => 'sometimes|nullable|string|max:120',
            'sla_hours' => 'sometimes|integer|min:1|max:720',
            'is_active' => 'sometimes|boolean',
        ]);

        $dept->update($validated);

        AuditLog::record($request->user(), 'setting_change', "Updated department \"{$dept->name}\"");

        return response()->json($dept->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $dept = Department::withCount('submissions')->findOrFail($id);

        if ($dept->submissions_count > 0) {
            return response()->json([
                'message' => 'Cannot delete a department with existing submissions. Deactivate it instead.',
            ], 422);
        }

        $dept->update(['is_active' => false]);

        AuditLog::record($request->user(), 'setting_change', "Deactivated department \"{$dept->name}\"");

        return response()->json(['message' => 'Department deactivated.']);
    }
}
