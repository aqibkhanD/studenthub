<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class AuthTest extends TestCase
{
    // ================================================================
    // Login
    // ================================================================

    public function test_student_can_login_with_correct_credentials(): void
    {
        $user = $this->makeStudent(['password' => 'Secret@123']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Secret@123',
        ])->assertOk()
          ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->makeStudent();

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->makeStudent(['is_active' => false, 'password' => 'Secret@123']);

        $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Secret@123',
        ])->assertStatus(403);
    }

    public function test_login_validation_rejects_missing_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
    }

    // ================================================================
    // Register
    // ================================================================

    public function test_new_student_can_register(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Rahim Uddin',
            'email'                 => 'rahim@diu.edu.bd',
            'student_id'            => 'DIU-99001',
            'phone'                 => '01700000001',
            'program'               => 'CSE',
            'batch'                 => 'Fall 2023',
            'password'              => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ])->assertStatus(201)
          ->assertJsonStructure(['token', 'user']);
    }

    public function test_register_fails_when_email_already_taken(): void
    {
        $existing = $this->makeStudent();

        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Someone Else',
            'email'                 => $existing->email,
            'student_id'            => 'DIU-99002',
            'phone'                 => '01700000002',
            'program'               => 'CSE',
            'batch'                 => 'Fall 2023',
            'password'              => 'Secret@123',
            'password_confirmation' => 'Secret@123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_when_password_confirmation_mismatch(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Test User',
            'email'                 => 'test2@diu.edu.bd',
            'student_id'            => 'DIU-99003',
            'phone'                 => '01700000003',
            'program'               => 'CSE',
            'batch'                 => 'Fall 2023',
            'password'              => 'Secret@123',
            'password_confirmation' => 'Different@123',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['password']);
    }

    // ================================================================
    // Profile
    // ================================================================

    public function test_authenticated_user_can_fetch_own_profile(): void
    {
        $user = $this->makeStudent();

        $this->withHeaders($this->headersFor($user))
             ->getJson('/api/v1/auth/profile')
             ->assertOk()
             ->assertJsonPath('user.id', $user->id);
    }

    public function test_unauthenticated_request_cannot_fetch_profile(): void
    {
        $this->getJson('/api/v1/auth/profile')->assertStatus(401);
    }

    public function test_student_can_update_own_profile(): void
    {
        $user = $this->makeStudent();

        $this->withHeaders($this->headersFor($user))
             ->putJson('/api/v1/auth/profile', [
                 'name'     => 'Updated Name',
                 'phone'    => '01811111111',
                 'program'  => 'EEE',
                 'semester' => '3rd',
             ])->assertOk()
               ->assertJsonPath('user.name', 'Updated Name');
    }

    // ================================================================
    // Change password
    // ================================================================

    public function test_user_can_change_password_with_correct_current(): void
    {
        $user = $this->makeStudent(['password' => 'Old@1234']);

        $this->withHeaders($this->headersFor($user))
             ->postJson('/api/v1/auth/change-password', [
                 'current_password'      => 'Old@1234',
                 'password'              => 'New@1234',
                 'password_confirmation' => 'New@1234',
             ])->assertOk();
    }

    public function test_change_password_fails_with_wrong_current(): void
    {
        $user = $this->makeStudent(['password' => 'Old@1234']);

        $this->withHeaders($this->headersFor($user))
             ->postJson('/api/v1/auth/change-password', [
                 'current_password'      => 'Wrong@1234',
                 'password'              => 'New@1234',
                 'password_confirmation' => 'New@1234',
             ])->assertStatus(422);
    }

    // ================================================================
    // Logout
    // ================================================================

    public function test_user_can_logout(): void
    {
        $user = $this->makeStudent();

        $this->withHeaders($this->headersFor($user))
             ->postJson('/api/v1/auth/logout')
             ->assertOk();
    }
}
