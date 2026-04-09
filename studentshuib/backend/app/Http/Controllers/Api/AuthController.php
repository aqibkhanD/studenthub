<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AuditService $audit) {}

    // POST /api/v1/auth/login
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
            return response()->json(['message' => 'Your account is inactive. Please contact support.'], 403);
        }

        $token = $user->createToken('api-token', ['*'], now()->addDays(7))->plainTextToken;

        // Track last login time
        $user->update(['last_login_at' => now()]);

        $this->audit->log($user->id, 'auth.login', 'User', $user->id, null, null, $request->ip(), $request->userAgent());

        return response()->json([
            'token' => $token,
            'user'  => $this->userResource($user),
        ]);
    }

    // POST /api/v1/auth/register
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|string|max:20|unique:users,student_id',
            'name'       => 'required|string|max:150',
            'email'      => 'required|email|unique:users,email',
            'phone'      => 'required|string|max:20',
            'password'   => 'required|string|min:8|confirmed',
            'program'    => 'nullable|string|max:100',
            'batch'      => 'nullable|string|max:20',
        ]);

        $user = User::create([
            ...$data,
            'role'     => 'student',
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $this->userResource($user),
        ], 201);
    }

    // POST /api/v1/auth/logout
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }

    // GET /api/v1/auth/me
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userResource($request->user()->load('department'))]);
    }

    // PUT /api/v1/auth/profile
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'    => 'sometimes|string|max:150',
            'phone'   => 'sometimes|string|max:20',
            'program' => 'sometimes|string|max:100',
            'batch'   => 'sometimes|string|max:20',
            'semester'=> 'sometimes|string|max:20',
        ]);

        $user->update($data);

        return response()->json(['user' => $this->userResource($user)]);
    }

    // PUT /api/v1/auth/password
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    // ----------------------------------------------------------
    private function userResource(User $user): array
    {
        return [
            'id'            => $user->id,
            'student_id'    => $user->student_id,
            'name'          => $user->name,
            'email'         => $user->email,
            'phone'         => $user->phone,
            'role'          => $user->role,
            'program'       => $user->program,
            'batch'         => $user->batch,
            'semester'      => $user->semester,
            'profile_photo' => $user->profile_photo,
            'department'    => $user->department ? [
                'id'   => $user->department->id,
                'name' => $user->department->name,
            ] : null,
            'is_active'     => $user->is_active,
        ];
    }

    // POST /api/v1/auth/forgot-password
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Always return the same message to prevent email enumeration
        $genericMessage = 'If an account with that email exists, a reset code has been sent.';

        if (! $user) {
            return response()->json(['message' => $genericMessage]);
        }

        // Generate a 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed token — one token per email (upsert by email PK)
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token'      => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        // Send OTP via email (uses MAIL_MAILER from .env — defaults to 'log' in dev)
        try {
            Mail::raw(
                "Your StudentsHub password reset code is: {$otp}\n\nThis code expires in 15 minutes.\nIf you did not request this, please ignore this message.",
                function ($message) use ($user) {
                    $message->to($user->email, $user->name)
                            ->subject('StudentsHub — Password Reset Code');
                }
            );
        } catch (\Throwable) {
            // Swallow mail failures silently — code is still in DB for dev inspection
        }

        $this->audit->log($user->id, 'auth.password_reset_requested', 'User', $user->id, null, null, $request->ip(), $request->userAgent());

        return response()->json(['message' => $genericMessage]);
    }

    // POST /api/v1/auth/reset-password
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        // Check token exists, matches, and was created within last 15 minutes
        if (
            ! $record ||
            ! Hash::check($data['token'], $record->token) ||
            now()->diffInMinutes($record->created_at) > 15
        ) {
            throw ValidationException::withMessages([
                'token' => ['The reset code is invalid or has expired.'],
            ]);
        }

        $user = User::where('email', $data['email'])->firstOrFail();
        $user->update(['password' => Hash::make($data['password'])]);

        // Invalidate all existing sessions
        $user->tokens()->delete();

        // Remove the used reset token
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        $this->audit->log($user->id, 'auth.password_reset', 'User', $user->id, null, null, $request->ip(), $request->userAgent());

        return response()->json(['message' => 'Password reset successfully. Please log in with your new password.']);
    }
}
