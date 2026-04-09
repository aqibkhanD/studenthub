<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FormType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormTypeController extends Controller
{
    // ── Public (student-facing) ────────────────────────────────────

    /**
     * GET /api/student/form-types
     * Returns active form types grouped by category for the browse screen.
     */
    public function publicIndex(): JsonResponse
    {
        $types = FormType::where('is_active', true)
            ->with('department:id,name,sla_hours')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get([
                'id', 'slug', 'name', 'category', 'department_id',
                'sla_hours', 'requires_docs', 'allow_anonymous',
                'auto_generate', 'instructions', 'fields',
            ]);

        return response()->json($types->groupBy('category'));
    }

    /**
     * GET /api/student/form-types/{slug}
     */
    public function publicShow(string $slug): JsonResponse
    {
        $type = FormType::where('slug', $slug)
            ->where('is_active', true)
            ->with('department:id,name,email,sla_hours')
            ->firstOrFail();

        return response()->json($type);
    }

    // ── Admin (super_admin only) ───────────────────────────────────

    /**
     * GET /api/admin/form-types
     */
    public function index(): JsonResponse
    {
        return response()->json(
            FormType::with('department:id,name,code')
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * POST /api/admin/form-types
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateFormType($request);

        $formType = FormType::create($validated);

        AuditLog::record(
            $request->user(), 'setting_change',
            "Created form type \"{$formType->name}\" (slug: {$formType->slug})"
        );

        return response()->json($formType->load('department'), 201);
    }

    /**
     * GET /api/admin/form-types/{id}
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(FormType::with('department')->findOrFail($id));
    }

    /**
     * PUT /api/admin/form-types/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $formType  = FormType::findOrFail($id);
        $validated = $this->validateFormType($request, $id);

        $formType->update($validated);

        AuditLog::record(
            $request->user(), 'setting_change',
            "Updated form type \"{$formType->name}\""
        );

        return response()->json($formType->fresh()->load('department'));
    }

    /**
     * DELETE /api/admin/form-types/{id}
     * Soft-delete: sets is_active = false rather than destroying the record
     * so existing submissions keep their foreign key reference.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $formType = FormType::findOrFail($id);
        $formType->update(['is_active' => false]);

        AuditLog::record(
            $request->user(), 'setting_change',
            "Deactivated form type \"{$formType->name}\""
        );

        return response()->json(['message' => 'Form type deactivated.']);
    }

    // ── Shared validation ──────────────────────────────────────────

    private function validateFormType(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'slug'            => "required|string|max:60|unique:form_types,slug" . ($ignoreId ? ",{$ignoreId}" : ''),
            'name'            => 'required|string|max:120',
            'category'        => 'required|in:academic_certification,complaint,career_counseling,club_cocurricular,finance,it_support,other',
            'department_id'   => 'required|exists:departments,id',
            'sla_hours'       => 'required|integer|min:1|max:720',
            'requires_docs'   => 'boolean',
            'allow_anonymous' => 'boolean',
            'auto_generate'   => 'boolean',
            'instructions'    => 'nullable|string|max:2000',
            'fields'          => 'required|array|min:1',
            'fields.*.key'    => 'required|string|max:60',
            'fields.*.label'  => 'required|string|max:120',
            'fields.*.type'   => 'required|in:text,textarea,select,radio,checkbox,date,file',
            'fields.*.required' => 'boolean',
            'fields.*.options'  => 'sometimes|array',
            'is_active'       => 'boolean',
            'sort_order'      => 'integer',
        ]);
    }
}
