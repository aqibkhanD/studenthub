<?php

namespace Tests\Feature\Admin;

use App\Models\Submission;
use Tests\TestCase;

class AdminSubmissionTest extends TestCase
{
    // ================================================================
    // Inbox / listing
    // ================================================================

    public function test_admin_can_view_submission_inbox(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $this->makeSubmission($student, $formType);

        $this->withHeaders($this->headersFor($admin))
             ->getJson('/api/v1/admin/submissions')
             ->assertOk()
             ->assertJsonStructure(['submissions']);
    }

    public function test_admin_only_sees_own_department_submissions(): void
    {
        $dept1    = $this->makeDepartment();
        $dept2    = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept1);
        $student  = $this->makeStudent();
        $ft1      = $this->makeFormType($dept1);
        $ft2      = $this->makeFormType($dept2);
        $sub1     = $this->makeSubmission($student, $ft1);
        $sub2     = $this->makeSubmission($student, $ft2);

        $response = $this->withHeaders($this->headersFor($admin))
                         ->getJson('/api/v1/admin/submissions')
                         ->assertOk();

        $refs = collect($response->json('submissions.data'))->pluck('reference_no');
        $this->assertContains($sub1->reference_no, $refs);
        $this->assertNotContains($sub2->reference_no, $refs);
    }

    public function test_super_admin_sees_all_department_submissions(): void
    {
        $dept1    = $this->makeDepartment();
        $dept2    = $this->makeDepartment();
        $super    = $this->makeSuperAdmin();
        $student  = $this->makeStudent();
        $ft1      = $this->makeFormType($dept1);
        $ft2      = $this->makeFormType($dept2);
        $sub1     = $this->makeSubmission($student, $ft1);
        $sub2     = $this->makeSubmission($student, $ft2);

        $response = $this->withHeaders($this->headersFor($super))
                         ->getJson('/api/v1/admin/submissions')
                         ->assertOk();

        $refs = collect($response->json('submissions.data'))->pluck('reference_no');
        $this->assertContains($sub1->reference_no, $refs);
        $this->assertContains($sub2->reference_no, $refs);
    }

    public function test_admin_can_filter_submissions_by_status(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $pending  = $this->makeSubmission($student, $formType, 'submitted');
        $done     = $this->makeSubmission($student, $formType, 'approved');

        $response = $this->withHeaders($this->headersFor($admin))
                         ->getJson('/api/v1/admin/submissions?status=submitted')
                         ->assertOk();

        $refs = collect($response->json('submissions.data'))->pluck('reference_no');
        $this->assertContains($pending->reference_no, $refs);
        $this->assertNotContains($done->reference_no, $refs);
    }

    // ================================================================
    // Status updates
    // ================================================================

    public function test_admin_can_approve_a_submission(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($admin))
             ->patchJson("/api/v1/admin/submissions/{$sub->reference_no}/status", [
                 'status'  => 'approved',
                 'comment' => 'Approved. Certificate will be ready in 2 days.',
             ])->assertOk()
               ->assertJsonPath('submission.status', 'approved');
    }

    public function test_admin_can_reject_a_submission_with_comment(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($admin))
             ->patchJson("/api/v1/admin/submissions/{$sub->reference_no}/status", [
                 'status'  => 'rejected',
                 'comment' => 'Incomplete documents provided.',
             ])->assertOk()
               ->assertJsonPath('submission.status', 'rejected');
    }

    public function test_rejection_requires_comment(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($admin))
             ->patchJson("/api/v1/admin/submissions/{$sub->reference_no}/status", [
                 'status' => 'rejected',
                 // intentionally no comment
             ])->assertStatus(422)
               ->assertJsonValidationErrors(['comment']);
    }

    public function test_return_requires_comment(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($admin))
             ->patchJson("/api/v1/admin/submissions/{$sub->reference_no}/status", [
                 'status' => 'returned',
             ])->assertStatus(422)
               ->assertJsonValidationErrors(['comment']);
    }

    public function test_admin_cannot_update_submission_from_another_department(): void
    {
        $dept1    = $this->makeDepartment();
        $dept2    = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept1);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept2);  // dept2's form
        $sub      = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($admin))
             ->patchJson("/api/v1/admin/submissions/{$sub->reference_no}/status", [
                 'status'  => 'approved',
                 'comment' => 'Approved.',
             ])->assertStatus(403);
    }

    // ================================================================
    // CSV export
    // ================================================================

    public function test_admin_can_export_submissions_as_csv(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $this->makeSubmission($student, $formType);

        $response = $this->withHeaders($this->headersFor($admin))
                         ->get('/api/v1/admin/submissions/export')
                         ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type') ?? '');
    }
}
