<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

    // GET /api/v1/super/users
    public function index(Request $request): JsonResponse
    {
        $users = User::with('department:id,name')
            ->when($request->filled('role'),          fn($q) => $q->where('role', $request->role))
            ->when($request->filled('department_id'), fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('active'),        fn($q) => $q->where('is_active', $request->boolean('active')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->search;
                $q->where(fn($q2) => $q2->where('name', 'ilike', "%{$s}%")
                    ->orWhere('email', 'ilike', "%{$s}%")
                    ->orWhere('student_id', 'ilike', "%{$s}%"));
            })
            ->orderBy('name')
            ->paginate(25);

        return response()->json($users);
    }

    // POST /api/v1/super/users
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8',
            'role'          => 'required|in:student,admin,dept_head,super_admin,management',
            'department_id' => 'nullable|exists:departments,id',
            'phone'         => 'nullable|string|max:20',
            'student_id'    => 'nullable|string|max:20|unique:users,student_id',
            'program'       => 'nullable|string|max:100',
            'batch'         => 'nullable|string|max:20',
        ]);

        $user = User::create([...$data, 'password' => Hash::make($data['password'])]);
        $this->audit->log($request->user()->id, 'user.created', 'User', $user->id);

        return response()->json(['user' => $user->load('department:id,name')], 201);
    }

    // GET /api/v1/super/users/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(['user' => User::with('department:id,name')->findOrFail($id)]);
    }

    // PUT /api/v1/super/users/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'          => 'sometimes|string|max:150',
            'email'         => "sometimes|email|unique:users,email,{$id}",
            'role'          => 'sometimes|in:student,admin,dept_head,super_admin,management',
            'department_id' => 'nullable|exists:departments,id',
            'phone'         => 'nullable|string|max:20',
            'program'       => 'nullable|string|max:100',
            'batch'         => 'nullable|string|max:20',
            'semester'      => 'nullable|string|max:20',
        ]);

        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:8']);
            $data['password'] = Hash::make($request->password);
        }

        $old = $user->toArray();
        $user->update($data);
        $this->audit->log($request->user()->id, 'user.updated', 'User', $id, $old, $data);

        return response()->json(['user' => $user->fresh('department:id,name')]);
    }

    // DELETE /api/v1/super/users/{id}
    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user = User::findOrFail($id);
        $user->update(['is_active' => false]); // soft-deactivate, not hard delete
        $this->audit->log($request->user()->id, 'user.deactivated', 'User', $id);

        return response()->json(['message' => 'User deactivated.']);
    }

    // GET /api/v1/admin/staff  — staff list for assignment dropdown
    public function staffList(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = User::where('is_active', true)
            ->whereIn('role', ['admin', 'dept_head', 'super_admin'])
            ->with('department:id,name')
            ->orderBy('name');

        // Dept-scoped admin sees only staff in their own department
        if ($user->role === 'admin' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        return response()->json($query->get(['id', 'name', 'email', 'role', 'department_id']));
    }

    // PUT /api/v1/super/users/{id}/toggle-active
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        $action = $user->is_active ? 'user.activated' : 'user.deactivated';
        $this->audit->log($request->user()->id, $action, 'User', $id);
        return response()->json(['is_active' => $user->is_active]);
    }
}
