<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormTypeRequest;
use App\Models\FormField;
use App\Models\FormType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Full CRUD for form types and their field definitions.
 *
 * Routes (all under middleware ['auth:sanctum', 'role:admin,super_admin']):
 *   GET    /admin/form-types                  → index()
 *   POST   /admin/form-types                  → store()
 *   GET    /admin/form-types/{formType}        → show()
 *   PUT    /admin/form-types/{formType}        → update()
 *   DELETE /admin/form-types/{formType}        → destroy()
 *   POST   /admin/form-types/{formType}/toggle → toggleActive()
 *
 * Field management (nested):
 *   GET    /admin/form-types/{formType}/fields               → fieldsIndex()
 *   POST   /admin/form-types/{formType}/fields               → fieldsStore()
 *   PUT    /admin/form-types/{formType}/fields/{field}       → fieldsUpdate()
 *   DELETE /admin/form-types/{formType}/fields/{field}       → fieldsDestroy()
 *   POST   /admin/form-types/{formType}/fields/reorder       → fieldsReorder()
 */
class FormTypeController extends Controller
{
    // ------------------------------------------------------------------
    // Form Types — CRUD
    // ------------------------------------------------------------------

    /**
     * GET /admin/form-types
     * Supports ?search=, ?category=, ?department_id=, ?active=
     */
    public function index(Request $request): JsonResponse
    {
        $query = FormType::with(['department', 'fields'])
            ->withCount('submissions');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($departmentId = $request->query('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($request->has('active')) {
            $query->where('is_active', (bool) $request->query('active'));
        }

        $formTypes = $query->orderBy('category')->orderBy('name')->get();

        return response()->json([
            'data' => $formTypes->map(fn($ft) => $this->formatFormType($ft)),
        ]);
    }

    /**
     * POST /admin/form-types
     */
    public function store(StoreFormTypeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $formType = DB::transaction(function () use ($data) {
            $formType = FormType::create([
                'name'               => $data['name'],
                'slug'               => $data['slug'],
                'category'           => $data['category'],
                'department_id'      => $data['department_id'],
                'description'        => $data['description'] ?? null,
                'sla_hours'          => $data['sla_hours'],
                'requires_documents' => $data['requires_documents'] ?? false,
                'allow_anonymous'    => $data['allow_anonymous'] ?? false,
                'auto_generate_doc'  => $data['auto_generate_doc'] ?? false,
                'is_active'          => $data['is_active'] ?? true,
            ]);

            $this->syncFields($formType, $data['fields'] ?? []);

            return $formType;
        });

        return response()->json([
            'message' => 'Form type created successfully.',
            'data'    => $this->formatFormType($formType->fresh(['department', 'fields'])),
        ], 201);
    }

    /**
     * GET /admin/form-types/{formType}
     */
    public function show(FormType $formType): JsonResponse
    {
        return response()->json([
            'data' => $this->formatFormType(
                $formType->load(['department', 'fields'])->loadCount('submissions')
            ),
        ]);
    }

    /**
     * PUT /admin/form-types/{formType}
     */
    public function update(StoreFormTypeRequest $request, FormType $formType): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($formType, $data) {
            $formType->update([
                'name'               => $data['name'],
                'slug'               => $data['slug'],
                'category'           => $data['category'],
                'department_id'      => $data['department_id'],
                'description'        => $data['description'] ?? null,
                'sla_hours'          => $data['sla_hours'],
                'requires_documents' => $data['requires_documents'] ?? false,
                'allow_anonymous'    => $data['allow_anonymous'] ?? false,
                'auto_generate_doc'  => $data['auto_generate_doc'] ?? false,
                'is_active'          => $data['is_active'] ?? $formType->is_active,
            ]);

            if (array_key_exists('fields', $data)) {
                $this->syncFields($formType, $data['fields']);
            }
        });

        return response()->json([
            'message' => 'Form type updated successfully.',
            'data'    => $this->formatFormType($formType->fresh(['department', 'fields'])),
        ]);
    }

    /**
     * DELETE /admin/form-types/{formType}
     *
     * Hard delete only if no submissions exist; otherwise soft-deactivate.
     */
    public function destroy(FormType $formType): JsonResponse
    {
        if ($formType->submissions()->exists()) {
            // Cannot delete — submissions reference this form type.
            // Deactivate instead and inform the caller.
            $formType->update(['is_active' => false]);

            return response()->json([
                'message'     => 'This form type has existing submissions and cannot be deleted. It has been deactivated instead.',
                'deactivated' => true,
            ], 200);
        }

        DB::transaction(function () use ($formType) {
            $formType->fields()->delete();
            $formType->delete();
        });

        return response()->json(['message' => 'Form type deleted successfully.']);
    }

    /**
     * POST /admin/form-types/{formType}/toggle
     * Toggle the is_active flag.
     */
    public function toggleActive(FormType $formType): JsonResponse
    {
        $formType->update(['is_active' => !$formType->is_active]);

        return response()->json([
            'message'   => 'Form type ' . ($formType->is_active ? 'activated' : 'deactivated') . '.',
            'is_active' => $formType->is_active,
        ]);
    }

    // ------------------------------------------------------------------
    // Field management — nested under a FormType
    // ------------------------------------------------------------------

    /**
     * GET /admin/form-types/{formType}/fields
     */
    public function fieldsIndex(FormType $formType): JsonResponse
    {
        return response()->json([
            'data' => $formType->fields()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * POST /admin/form-types/{formType}/fields
     */
    public function fieldsStore(Request $request, FormType $formType): JsonResponse
    {
        $data = $request->validate([
            'label'       => ['required', 'string', 'max:120'],
            'field_type'  => ['required', 'string', 'in:text,textarea,email,phone,number,date,select,radio,checkbox,file'],
            'is_required' => ['boolean'],
            'options'     => ['nullable', 'string', 'max:1000'],
            'sort_order'  => ['integer', 'min:0'],
        ]);

        $field = $formType->fields()->create([
            'label'       => $data['label'],
            'field_name'  => Str::snake(strtolower($data['label'])),
            'field_type'  => $data['field_type'],
            'is_required' => $data['is_required'] ?? false,
            'options'     => $data['options'] ?? null,
            'sort_order'  => $data['sort_order'] ?? ($formType->fields()->max('sort_order') + 1),
        ]);

        return response()->json([
            'message' => 'Field added.',
            'data'    => $field,
        ], 201);
    }

    /**
     * PUT /admin/form-types/{formType}/fields/{field}
     */
    public function fieldsUpdate(Request $request, FormType $formType, FormField $field): JsonResponse
    {
        $this->ensureFieldBelongsToFormType($field, $formType);

        $data = $request->validate([
            'label'       => ['sometimes', 'required', 'string', 'max:120'],
            'field_type'  => ['sometimes', 'required', 'string', 'in:text,textarea,email,phone,number,date,select,radio,checkbox,file'],
            'is_required' => ['boolean'],
            'options'     => ['nullable', 'string', 'max:1000'],
            'sort_order'  => ['integer', 'min:0'],
        ]);

        if (isset($data['label'])) {
            $data['field_name'] = Str::snake(strtolower($data['label']));
        }

        $field->update($data);

        return response()->json([
            'message' => 'Field updated.',
            'data'    => $field->fresh(),
        ]);
    }

    /**
     * DELETE /admin/form-types/{formType}/fields/{field}
     */
    public function fieldsDestroy(FormType $formType, FormField $field): JsonResponse
    {
        $this->ensureFieldBelongsToFormType($field, $formType);
        $field->delete();

        return response()->json(['message' => 'Field removed.']);
    }

    /**
     * POST /admin/form-types/{formType}/fields/reorder
     * Accepts: { order: [{ id: 1, sort_order: 0 }, ...] }
     */
    public function fieldsReorder(Request $request, FormType $formType): JsonResponse
    {
        $data = $request->validate([
            'order'              => ['required', 'array'],
            'order.*.id'         => ['required', 'integer'],
            'order.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($formType, $data) {
            foreach ($data['order'] as $item) {
                FormField::where('id', $item['id'])
                    ->where('form_type_id', $formType->id)
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json([
            'message' => 'Field order saved.',
            'data'    => $formType->fields()->orderBy('sort_order')->get(),
        ]);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function formatFormType(FormType $formType): array
    {
        return [
            'id'                 => $formType->id,
            'name'               => $formType->name,
            'slug'               => $formType->slug,
            'category'           => $formType->category,
            'category_label'     => ucwords(str_replace('_', ' ', $formType->category)),
            'department_id'      => $formType->department_id,
            'department_name'    => $formType->department?->name,
            'description'        => $formType->description,
            'sla_hours'          => $formType->sla_hours,
            'sla_label'          => $this->slaLabel($formType->sla_hours),
            'requires_documents' => (bool) $formType->requires_documents,
            'allow_anonymous'    => (bool) $formType->allow_anonymous,
            'auto_generate_doc'  => (bool) $formType->auto_generate_doc,
            'is_active'          => (bool) $formType->is_active,
            'submissions_count'  => $formType->submissions_count ?? null,
            'fields'             => $formType->relationLoaded('fields')
                ? $formType->fields->sortBy('sort_order')->values()
                : null,
        ];
    }

    /**
     * Replace all fields for a form type with the given array.
     * Existing fields NOT in the new array are deleted.
     * Existing fields with an 'id' key are updated in place.
     * Entries without 'id' are inserted as new fields.
     */
    private function syncFields(FormType $formType, array $fields): void
    {
        $incomingIds = collect($fields)->pluck('id')->filter()->values()->toArray();

        // Delete fields not present in the new payload
        if (!empty($incomingIds)) {
            $formType->fields()->whereNotIn('id', $incomingIds)->delete();
        } else {
            $formType->fields()->delete();
        }

        foreach ($fields as $index => $fieldData) {
            $attributes = [
                'form_type_id' => $formType->id,
                'label'        => $fieldData['label'],
                'field_name'   => Str::snake(strtolower($fieldData['label'])),
                'field_type'   => $fieldData['field_type'],
                'is_required'  => $fieldData['is_required'] ?? false,
                'options'      => $fieldData['options'] ?? null,
                'sort_order'   => $fieldData['sort_order'] ?? $index,
            ];

            if (!empty($fieldData['id'])) {
                FormField::where('id', $fieldData['id'])
                    ->where('form_type_id', $formType->id)
                    ->update($attributes);
            } else {
                FormField::create($attributes);
            }
        }
    }

    private function slaLabel(int $hours): string
    {
        if ($hours < 24) {
            return "{$hours}h";
        }
        $days = intdiv($hours, 24);
        return "{$days}d";
    }

    private function ensureFieldBelongsToFormType(FormField $field, FormType $formType): void
    {
        if ($field->form_type_id !== $formType->id) {
            abort(404, 'Field not found for this form type.');
        }
    }
}
