<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Your account is inactive. Contact admin.'], 403);
        }

        // Revoke previous tokens for this device type
        $user->tokens()->where('name', 'api')->delete();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'phone'         => $user->phone,
                'role'          => $user->role,
                'student_id'    => $user->student_id,
                'department_id' => $user->department_id,
                'program'       => $user->program,
            ],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:150',
            'email'      => 'required|email|unique:users',
            'student_id' => 'required|string|unique:users|max:20',
            'phone'      => 'required|string|max:20',
            'password'   => 'required|string|min:8|confirmed',
            'program'    => 'nullable|string|max:100',
            'batch'      => 'nullable|string|max:20',
            'semester'   => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'student_id' => $request->student_id,
            'phone'      => $request->phone,
            'password'   => Hash::make($request->password),
            'role'       => 'student',
            'program'    => $request->program,
            'batch'      => $request->batch,
            'semester'   => $request->semester,
            'is_active'  => true,
        ]);

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'token'   => $token,
            'user'    => $user->only(['id', 'name', 'email', 'student_id', 'role']),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('department');
        return response()->json([
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'role'          => $user->role,
            'student_id'    => $user->student_id,
            'department'    => $user->department?->only(['id', 'name', 'slug']),
            'program'       => $user->program,
            'batch'         => $user->batch,
            'semester'      => $user->semester,
            'unread_notifications' => $user->unreadNotificationsCount(),
        ]);
    }
}
