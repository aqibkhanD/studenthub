<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Smoke tests for role-based access control.
 *
 * These verify that the RoleMiddleware correctly denies access
 * when users attempt to reach routes outside their role.
 */
class RoleAccessTest extends TestCase
{
    // ================================================================
    // Unauthenticated
    // ================================================================

    public function test_unauthenticated_request_to_student_route_returns_401(): void
    {
        $this->getJson('/api/v1/student/submissions')->assertStatus(401);
    }

    public function test_unauthenticated_request_to_admin_route_returns_401(): void
    {
        $this->getJson('/api/v1/admin/submissions')->assertStatus(401);
    }

    public function test_unauthenticated_request_to_super_route_returns_401(): void
    {
        $this->getJson('/api/v1/super/users')->assertStatus(401);
    }

    // ================================================================
    // Student cannot access admin routes
    // ================================================================

    public function test_student_cannot_access_admin_inbox(): void
    {
        $student = $this->makeStudent();

        $this->withHeaders($this->headersFor($student))
             ->getJson('/api/v1/admin/submissions')
             ->assertStatus(403);
    }

    public function test_student_cannot_access_super_user_list(): void
    {
        $student = $this->makeStudent();

        $this->withHeaders($this->headersFor($student))
             ->getJson('/api/v1/super/users')
             ->assertStatus(403);
    }

    public function test_student_cannot_access_analytics(): void
    {
        $student = $this->makeStudent();

        $this->withHeaders($this->headersFor($student))
             ->getJson('/api/v1/super/analytics')
             ->assertStatus(403);
    }

    // ================================================================
    // Admin cannot access super-admin routes
    // ================================================================

    public function test_admin_cannot_access_super_user_management(): void
    {
        $dept  = $this->makeDepartment();
        $admin = $this->makeAdmin($dept);

        $this->withHeaders($this->headersFor($admin))
             ->getJson('/api/v1/super/users')
             ->assertStatus(403);
    }

    public function test_admin_cannot_create_departments(): void
    {
        $dept  = $this->makeDepartment();
        $admin = $this->makeAdmin($dept);

        $this->withHeaders($this->headersFor($admin))
             ->postJson('/api/v1/super/departments', [
                 'name' => 'Hacked Dept',
                 'slug' => 'hacked-dept',
                 'code' => 'HACK',
             ])->assertStatus(403);
    }

    // ================================================================
    // Management cannot access operational admin routes
    // ================================================================

    public function test_management_cannot_update_submission_status(): void
    {
        $dept     = $this->makeDepartment();
        $mgmt     = $this->makeManagement();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($mgmt))
             ->patchJson("/api/v1/admin/submissions/{$sub->reference_no}/status", [
                 'status'  => 'approved',
                 'comment' => 'Approved by management.',
             ])->assertStatus(403);
    }

    // ================================================================
    // Inactive user blocked
    // ================================================================

    public function test_inactive_user_token_is_rejected(): void
    {
        // Create user active, get token, then deactivate
        $user = $this->makeStudent();
        $headers = $this->headersFor($user);

        $user->update(['is_active' => false]);

        $this->withHeaders($headers)
             ->getJson('/api/v1/student/submissions')
             ->assertStatus(403);
    }

    // ================================================================
    // Super admin has full access
    // ================================================================

    public function test_super_admin_can_access_admin_routes(): void
    {
        $super = $this->makeSuperAdmin();

        $this->withHeaders($this->headersFor($super))
             ->getJson('/api/v1/admin/submissions')
             ->assertOk();
    }

    public function test_super_admin_can_access_super_routes(): void
    {
        $super = $this->makeSuperAdmin();

        $this->withHeaders($this->headersFor($super))
             ->getJson('/api/v1/super/users')
             ->assertOk();
    }
}
