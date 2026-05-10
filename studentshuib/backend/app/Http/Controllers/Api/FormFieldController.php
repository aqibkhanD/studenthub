<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormField;
use App\Models\FormType;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormFieldController extends Controller
{
    public function __construct(private AuditService $audit) {}

    // GET /api/v1/super/form-types/{id}/fields
    public function index(int $id): JsonResponse
    {
        $formType = FormType::findOrFail($id);
        return response()->json(['fields' => $formType->fields]);
    }

    // POST /api/v1/super/form-types/{id}/fields
    public function store(Request $request, int $id): JsonResponse
    {
        $formType = FormType::findOrFail($id);
        $data     = $this->validated($request, $id);

        // Auto-assign sort_order to the end of the list
        $data['sort_order']    = ($formType->fields()->max('sort_order') ?? -1) + 1;
        $data['form_type_id']  = $id;

        $field = FormField::create($data);

        $this->audit->log($request->user()->id, 'form_field.created', 'FormField', $field->id, null, $data);

        return response()->json(['field' => $field], 201);
    }

    // PUT /api/v1/super/form-types/{id}/fields/{fid}
    public function update(Request $request, int $id, int $fid): JsonResponse
    {
        $field = FormField::where('id', $fid)->where('form_type_id', $id)->firstOrFail();
        $old   = $field->toArray();
        $data  = $this->validated($request, $id, $fid);

        $field->update($data);

        $this->audit->log($request->user()->id, 'form_field.updated', 'FormField', $fid, $old, $data);

        return response()->json(['field' => $field->fresh()]);
    }

    // DELETE /api/v1/super/form-types/{id}/fields/{fid}
    public function destroy(Request $request, int $id, int $fid): JsonResponse
    {
        $field = FormField::where('id', $fid)->where('form_type_id', $id)->firstOrFail();
        $field->delete();

        $this->audit->log($request->user()->id, 'form_field.deleted', 'FormField', $fid);

        return response()->json(['message' => 'Field deleted.']);
    }

    // POST /api/v1/super/form-types/{id}/fields/reorder
    // Body: { "order": [fieldId, fieldId, ...] } — array index = desired sort_order
    public function reorder(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'order'   => 'required|array|min:1',
            'order.*' => 'integer',
        ]);

        foreach ($request->order as $sortOrder => $fieldId) {
            FormField::where('id', $fieldId)
                ->where('form_type_id', $id)
                ->update(['sort_order' => $sortOrder]);
        }

        return response()->json([
            'message' => 'Fields reordered.',
            'fields'  => FormType::findOrFail($id)->fields,
        ]);
    }

    // ----------------------------------------------------------
    private function validated(Request $request, int $formTypeId, ?int $fieldId = null): array
    {
        return $request->validate([
            'label'            => 'required|string|max:100',
            // field_key must be unique within the same form type (excluding self on update).
            // Use 'NULL' literal when $fieldId is null (CREATE case) — PostgreSQL rejects
            // empty string as bigint input, MySQL would silently coerce to 0.
            'field_key'        => [
                'required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                'unique:form_fields,field_key,' . ($fieldId ?? 'NULL') . ",id,form_type_id,{$formTypeId}",
            ],
            'field_type'       => 'required|in:text,textarea,select,checkbox,date,email,phone,number,file',
            'options'          => 'nullable|array',
            'options.*'        => 'string|max:150',
            'is_required'      => 'boolean',
            'placeholder'      => 'nullable|string|max:200',
            'help_text'        => 'nullable|string|max:500',
            'validation_rules' => 'nullable|string|max:500',
            'sort_order'       => 'integer|min:0',
            'is_active'        => 'boolean',
        ]);
    }
}
