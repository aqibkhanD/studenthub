<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Department management + signatory + SLA escalation rules.
 *
 * Routes (under ['auth:sanctum', 'role:admin,super_admin']):
 *   GET    /admin/departments                      → index()
 *   POST   /admin/departments                      → store()
 *   GET    /admin/departments/{department}          → show()
 *   PUT    /admin/departments/{department}          → update()
 *   DELETE /admin/departments/{department}          → destroy()
 *   POST   /admin/departments/{department}/toggle   → toggleActive()
 *   POST   /admin/departments/{department}/signatory-logo → uploadSignatoryLogo()
 *   DELETE /admin/departments/{department}/signatory-logo → deleteSignatoryLogo()
 */
class DepartmentController extends Controller
{
    // ------------------------------------------------------------------
    // Department CRUD
    // ------------------------------------------------------------------

    /**
     * GET /admin/departments
     * Supports ?search=, ?active=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::withCount(['formTypes', 'submissions'])
            ->with('slaEscalationRules');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->has('active')) {
            $query->where('is_active', (bool) $request->query('active'));
        }

        $departments = $query->orderBy('name')->get();

        return response()->json([
            'data' => $departments->map(fn($d) => $this->formatDepartment($d)),
        ]);
    }

    /**
     * POST /admin/departments
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $department = DB::transaction(function () use ($data) {
            $department = Department::create([
                'name'          => $data['name'],
                'slug'          => $data['slug'],
                'code'          => $data['code'] ?? null,
                'head_of_dept'  => $data['head_of_dept'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'is_active'     => $data['is_active'] ?? true,
            ]);

            if (!empty($data['signatory'])) {
                $this->writeSignatory($department->slug, $data['signatory']);
            }

            if (!empty($data['escalation_rules'])) {
                $this->syncEscalationRules($department, $data['escalation_rules']);
            }

            return $department;
        });

        return response()->json([
            'message' => 'Department created successfully.',
            'data'    => $this->formatDepartment($department->fresh(['slaEscalationRules'])),
        ], 201);
    }

    /**
     * GET /admin/departments/{department}
     */
    public function show(Department $department): JsonResponse
    {
        return response()->json([
            'data' => $this->formatDepartment(
                $department->load('slaEscalationRules')
                           ->loadCount(['formTypes', 'submissions'])
            ),
        ]);
    }

    /**
     * PUT /admin/departments/{department}
     */
    public function update(StoreDepartmentRequest $request, Department $department): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($department, $data) {
            $department->update([
                'name'          => $data['name'],
                'slug'          => $data['slug'],
                'code'          => $data['code'] ?? $department->code,
                'head_of_dept'  => $data['head_of_dept'] ?? $department->head_of_dept,
                'contact_email' => $data['contact_email'] ?? $department->contact_email,
                'contact_phone' => $data['contact_phone'] ?? $department->contact_phone,
                'is_active'     => $data['is_active'] ?? $department->is_active,
            ]);

            if (array_key_exists('signatory', $data)) {
                $this->writeSignatory($department->slug, $data['signatory']);
            }

            if (array_key_exists('escalation_rules', $data)) {
                $this->syncEscalationRules($department, $data['escalation_rules']);
            }
        });

        return response()->json([
            'message' => 'Department updated successfully.',
            'data'    => $this->formatDepartment($department->fresh(['slaEscalationRules'])),
        ]);
    }

    /**
     * DELETE /admin/departments/{department}
     *
     * Hard delete only if no form types or submissions are linked.
     */
    public function destroy(Department $department): JsonResponse
    {
        if ($department->formTypes()->exists() || $department->submissions()->exists()) {
            $department->update(['is_active' => false]);

            return response()->json([
                'message'     => 'Department has linked form types or submissions and cannot be deleted. It has been deactivated.',
                'deactivated' => true,
            ]);
        }

        DB::transaction(function () use ($department) {
            $department->slaEscalationRules()->delete();
            $this->deleteSignatory($department->slug);
            $department->delete();
        });

        return response()->json(['message' => 'Department deleted successfully.']);
    }

    /**
     * POST /admin/departments/{department}/toggle
     */
    public function toggleActive(Department $department): JsonResponse
    {
        $department->update(['is_active' => !$department->is_active]);

        return response()->json([
            'message'   => 'Department ' . ($department->is_active ? 'activated' : 'deactivated') . '.',
            'is_active' => $department->is_active,
        ]);
    }

    // ------------------------------------------------------------------
    // Signatory logo upload / delete
    // ------------------------------------------------------------------

    /**
     * POST /admin/departments/{department}/signatory-logo
     * Accepts: multipart/form-data with field 'signature'
     * Stores to: storage/app/private/signatures/{slug}.{ext}
     * Updates signatory config to reference the new file.
     */
    public function uploadSignatoryLogo(Request $request, Department $department): JsonResponse
    {
        $request->validate([
            'signature' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:1024'],
        ]);

        $file     = $request->file('signature');
        $ext      = strtolower($file->getClientOriginalExtension());
        $filename = $department->slug . '.' . $ext;

        // Remove existing signature file if it exists
        if (Storage::disk('local')->exists("private/signatures/{$department->slug}.png") ||
            Storage::disk('local')->exists("private/signatures/{$department->slug}.jpg") ||
            Storage::disk('local')->exists("private/signatures/{$department->slug}.jpeg")) {
            foreach (['png', 'jpg', 'jpeg'] as $oldExt) {
                Storage::disk('local')->delete("private/signatures/{$department->slug}.{$oldExt}");
            }
        }

        Storage::disk('local')->putFileAs('private/signatures', $file, $filename);

        // Update the signatory config with the new filename
        $current = $this->readSignatory($department->slug);
        $current['signature_image'] = $filename;
        $this->writeSignatory($department->slug, $current);

        return response()->json([
            'message'   => 'Signature uploaded successfully.',
            'filename'  => $filename,
        ]);
    }

    /**
     * DELETE /admin/departments/{department}/signatory-logo
     */
    public function deleteSignatoryLogo(Department $department): JsonResponse
    {
        foreach (['png', 'jpg', 'jpeg'] as $ext) {
            $path = "private/signatures/{$department->slug}.{$ext}";
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $current = $this->readSignatory($department->slug);
        $current['signature_image'] = null;
        $this->writeSignatory($department->slug, $current);

        return response()->json(['message' => 'Signature removed.']);
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function formatDepartment(Department $dept): array
    {
        return [
            'id'                  => $dept->id,
            'name'                => $dept->name,
            'slug'                => $dept->slug,
            'code'                => $dept->code,
            'head_of_dept'        => $dept->head_of_dept,
            'contact_email'       => $dept->contact_email,
            'contact_phone'       => $dept->contact_phone,
            'is_active'           => (bool) $dept->is_active,
            'form_types_count'    => $dept->form_types_count ?? null,
            'submissions_count'   => $dept->submissions_count ?? null,
            'signatory'           => $this->readSignatory($dept->slug),
            'escalation_rules'    => $dept->relationLoaded('slaEscalationRules')
                ? $dept->slaEscalationRules->toArray()
                : null,
        ];
    }

    /**
     * Read this department's signatory from config/signatories.php.
     * Falls back to an empty skeleton if the slug is not found.
     */
    private function readSignatory(string $slug): array
    {
        $all = config('signatories', []);

        return $all[$slug] ?? [
            'name'            => '',
            'title'           => '',
            'department'      => '',
            'institution'     => 'Daffodil International University',
            'phone'           => '',
            'email'           => '',
            'signature_image' => null,
        ];
    }

    /**
     * Persist a signatory entry back to config/signatories.php.
     *
     * This rewrites the entire PHP config file. It is called infrequently
     * (only when an admin saves department settings) so performance is fine.
     * The file is excluded from version control to avoid secrets leaking.
     */
    private function writeSignatory(string $slug, array $signatory): void
    {
        $configPath = config_path('signatories.php');
        $all        = file_exists($configPath) ? (include $configPath) : [];

        // Preserve existing signature_image if not provided in the new payload
        if (empty($signatory['signature_image']) && isset($all[$slug]['signature_image'])) {
            $signatory['signature_image'] = $all[$slug]['signature_image'];
        }

        $all[$slug] = [
            'name'            => $signatory['name'] ?? '',
            'title'           => $signatory['title'] ?? '',
            'department'      => $signatory['department'] ?? '',
            'institution'     => $signatory['institution'] ?? 'Daffodil International University',
            'phone'           => $signatory['phone'] ?? '',
            'email'           => $signatory['email'] ?? '',
            'signature_image' => $signatory['signature_image'] ?? null,
        ];

        $export = "<?php\n\n/**\n * Department signatory configuration.\n * Written by DepartmentController — do not edit manually.\n */\nreturn " . $this->varExport($all) . ";\n";
        file_put_contents($configPath, $export);

        // Clear Laravel's config cache so the change takes effect immediately
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($configPath, true);
        }
    }

    private function deleteSignatory(string $slug): void
    {
        $configPath = config_path('signatories.php');
        $all        = file_exists($configPath) ? (include $configPath) : [];

        unset($all[$slug]);

        $export = "<?php\n\nreturn " . $this->varExport($all) . ";\n";
        file_put_contents($configPath, $export);
    }

    /**
     * Replace all SLA escalation rules for a department.
     */
    private function syncEscalationRules(Department $department, array $rules): void
    {
        $department->slaEscalationRules()->delete();

        foreach ($rules as $rule) {
            $department->slaEscalationRules()->create([
                'hours'         => $rule['hours'],
                'action'        => $rule['action'],
                'notify_roles'  => json_encode($rule['notify_roles'] ?? ['admin']),
            ]);
        }
    }

    /**
     * A var_export() alternative that produces clean PHP array syntax.
     */
    private function varExport(array $data, int $indent = 0): string
    {
        $pad   = str_repeat('    ', $indent);
        $inner = str_repeat('    ', $indent + 1);
        $lines = ["[\n"];

        foreach ($data as $key => $value) {
            $k = is_string($key) ? "'" . addslashes($key) . "'" : $key;
            if (is_array($value)) {
                $lines[] = "{$inner}{$k} => " . $this->varExport($value, $indent + 1) . ",\n";
            } elseif (is_null($value)) {
                $lines[] = "{$inner}{$k} => null,\n";
            } elseif (is_bool($value)) {
                $lines[] = "{$inner}{$k} => " . ($value ? 'true' : 'false') . ",\n";
            } else {
                $lines[] = "{$inner}{$k} => '" . addslashes((string) $value) . "',\n";
            }
        }

        $lines[] = "{$pad}]";

        return implode('', $lines);
    }
}
