<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormType;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormTypeController extends Controller
{
    public function __construct(private AuditService $audit) {}

    // GET /api/v1/student/form-types  — public catalogue for students
    public function indexForStudent(Request $request): JsonResponse
    {
        $formTypes = FormType::with('department:id,name')
            ->where('is_active', true)
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->category))
            ->orderBy('sort_order')
            ->get()
            ->map(fn($ft) => [
                'id'                  => $ft->id,
                'name'                => $ft->name,
                'slug'                => $ft->slug,
                'category'            => $ft->category,
                'department'          => $ft->department ? ['id' => $ft->department->id, 'name' => $ft->department->name] : null,
                'instructions'        => $ft->instructions,
                'requires_documents'  => $ft->requires_documents,
                'allow_anonymous'     => $ft->allow_anonymous,
                'sla_hours'           => $ft->effectiveSlaHours(),
            ]);

        return response()->json(['form_types' => $formTypes]);
    }

    // GET /api/v1/student/form-types/{slug}  — full detail including fields
    public function showForStudent(Request $request, string $slug): JsonResponse
    {
        $formType = FormType::with(['fields' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'), 'department:id,name'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json(['form_type' => $formType]);
    }

    // ---- Super admin routes ----

    // GET /api/v1/super/form-types
    public function index(Request $request): JsonResponse
    {
        $formTypes = FormType::with('department:id,name')
            ->when($request->filled('category'),    fn($q) => $q->where('category', $request->category))
            ->when($request->filled('department_id'), fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('active'),      fn($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('sort_order')
            ->paginate(50);

        return response()->json($formTypes);
    }

    // POST /api/v1/super/form-types
    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $formType = FormType::create($data);
        $this->audit->log($request->user()->id, 'form_type.created', 'FormType', $formType->id, null, $data);
        return response()->json(['form_type' => $formType->load('department:id,name')], 201);
    }

    // GET /api/v1/super/form-types/{id}
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['form_type' => FormType::with(['department:id,name', 'fields'])->findOrFail($id)]);
    }

    // PUT /api/v1/super/form-types/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $formType = FormType::findOrFail($id);
        $data     = $this->validated($request);
        $old      = $formType->toArray();
        $formType->update($data);
        $this->audit->log($request->user()->id, 'form_type.updated', 'FormType', $id, $old, $data);
        return response()->json(['form_type' => $formType->fresh('department:id,name')]);
    }

    // DELETE /api/v1/super/form-types/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        $formType = FormType::findOrFail($id);

        if ($formType->submissions()->count() > 0) {
            return response()->json(['message' => 'Cannot delete a form type that has submissions. Deactivate it instead.'], 422);
        }

        $formType->delete();
        $this->audit->log($request->user()->id, 'form_type.deleted', 'FormType', $id);
        return response()->json(['message' => 'Form type deleted.']);
    }

    // PUT /api/v1/super/form-types/{id}/toggle-active
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $formType = FormType::findOrFail($id);
        $formType->update(['is_active' => !$formType->is_active]);
        $status = $formType->is_active ? 'activated' : 'deactivated';
        $this->audit->log($request->user()->id, "form_type.{$status}", 'FormType', $id);
        return response()->json(['is_active' => $formType->is_active, 'message' => "Form type {$status}."]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'               => 'required|string|max:150',
            'slug'               => 'required|string|max:100|unique:form_types,slug,' . ($request->route('id') ?? 'NULL'),
            'category'           => 'required|in:academic_certification,complaint,career_counseling,club_cocurricular,profile_portfolio,finance,it_support,other',
            'department_id'      => 'required|exists:departments,id',
            'description'        => 'nullable|string',
            'instructions'       => 'nullable|string',
            'requires_documents' => 'boolean',
            'allow_anonymous'    => 'boolean',
            'auto_generate_doc'  => 'boolean',
            'sla_hours'          => 'nullable|integer|min:1|max:720',
            'is_active'          => 'boolean',
            'sort_order'         => 'integer|min:0',
        ]);
    }
}
