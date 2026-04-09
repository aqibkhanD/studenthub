<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(private AuditService $audit) {}

    // GET /api/v1/admin/departments  — all admins (read only)
    public function index(Request $request): JsonResponse
    {
        $departments = Department::withCount('submissions')
            ->when($request->boolean('active_only', false), fn($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();

        return response()->json(['departments' => $departments]);
    }

    // GET /api/v1/admin/departments/{id}
    public function show(int $id): JsonResponse
    {
        $dept = Department::with(['head:id,name,email', 'formTypes:id,name,slug,is_active'])
            ->withCount('submissions')
            ->findOrFail($id);

        return response()->json(['department' => $dept]);
    }

    // ---- Super admin CRUD ----

    // POST /api/v1/super/departments
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $dept = Department::create($data);
        $this->audit->log($request->user()->id, 'department.created', 'Department', $dept->id, null, $data);
        return response()->json(['department' => $dept], 201);
    }

    // PUT /api/v1/super/departments/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);
        $data = $this->validated($request, $id);
        $old  = $dept->toArray();
        $dept->update($data);
        $this->audit->log($request->user()->id, 'department.updated', 'Department', $id, $old, $data);
        return response()->json(['department' => $dept->fresh()]);
    }

    // DELETE /api/v1/super/departments/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);

        if ($dept->submissions()->count() > 0 || $dept->formTypes()->count() > 0) {
            return response()->json(['message' => 'Cannot delete a department that has submissions or form types. Deactivate it instead.'], 422);
        }

        $dept->delete();
        $this->audit->log($request->user()->id, 'department.deleted', 'Department', $id);
        return response()->json(['message' => 'Department deleted.']);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name'         => 'required|string|max:150',
            'slug'         => 'required|string|max:100|unique:departments,slug,' . ($id ?? 'NULL'),
            'code'         => 'nullable|string|max:20',
            'description'  => 'nullable|string',
            'email'        => 'nullable|email',
            'phone'        => 'nullable|string|max:20',
            'head_user_id' => 'nullable|exists:users,id',
            'sla_hours'    => 'nullable|integer|min:1|max:720',
            'is_active'    => 'boolean',
        ]);
    }
}
