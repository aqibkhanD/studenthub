<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class BulkStatusTest extends TestCase
{
    // ================================================================
    // Bulk approve
    // ================================================================

    public function test_admin_can_bulk_approve_submissions(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);

        $sub1 = $this->makeSubmission($student, $formType, 'in_review');
        $sub2 = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($admin))
             ->postJson('/api/v1/admin/submissions/bulk-status', [
                 'reference_nos' => [$sub1->reference_no, $sub2->reference_no],
                 'status'        => 'approved',
                 'comment'       => 'Batch approved.',
             ])->assertOk()
               ->assertJsonPath('updated', 2);

        $this->assertEquals('approved', $sub1->fresh()->status);
        $this->assertEquals('approved', $sub2->fresh()->status);
    }

    // ================================================================
    // Comment required for reject/return in bulk
    // ================================================================

    public function test_bulk_reject_requires_comment(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($admin))
             ->postJson('/api/v1/admin/submissions/bulk-status', [
                 'reference_nos' => [$sub->reference_no],
                 'status'        => 'rejected',
             ])->assertStatus(422)
               ->assertJsonValidationErrors(['comment']);
    }

    public function test_bulk_return_requires_comment(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'in_review');

        $this->withHeaders($this->headersFor($admin))
             ->postJson('/api/v1/admin/submissions/bulk-status', [
                 'reference_nos' => [$sub->reference_no],
                 'status'        => 'returned',
             ])->assertStatus(422)
               ->assertJsonValidationErrors(['comment']);
    }

    // ================================================================
    // Cross-department protection in bulk
    // ================================================================

    public function test_bulk_update_skips_cross_department_submissions(): void
    {
        $dept1    = $this->makeDepartment();
        $dept2    = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept1);   // scoped to dept1
        $student  = $this->makeStudent();
        $ft1      = $this->makeFormType($dept1);
        $ft2      = $this->makeFormType($dept2);
        $ownSub   = $this->makeSubmission($student, $ft1, 'in_review');
        $otherSub = $this->makeSubmission($student, $ft2, 'in_review');

        $response = $this->withHeaders($this->headersFor($admin))
                         ->postJson('/api/v1/admin/submissions/bulk-status', [
                             'reference_nos' => [$ownSub->reference_no, $otherSub->reference_no],
                             'status'        => 'approved',
                             'comment'       => 'Bulk approval.',
                         ])->assertOk();

        // Only the dept1 submission should be updated
        $this->assertEquals('approved',  $ownSub->fresh()->status);
        $this->assertEquals('in_review', $otherSub->fresh()->status);
        $this->assertEquals(1, $response->json('updated'));
    }

    // ================================================================
    // Validation
    // ================================================================

    public function test_bulk_update_validates_max_100_submissions(): void
    {
        $dept    = $this->makeDepartment();
        $admin   = $this->makeAdmin($dept);
        $tooMany = array_fill(0, 101, 'DIU-FAKE-9999');

        $this->withHeaders($this->headersFor($admin))
             ->postJson('/api/v1/admin/submissions/bulk-status', [
                 'reference_nos' => $tooMany,
                 'status'        => 'approved',
             ])->assertStatus(422)
               ->assertJsonValidationErrors(['reference_nos']);
    }

    public function test_bulk_update_requires_reference_nos(): void
    {
        $dept  = $this->makeDepartment();
        $admin = $this->makeAdmin($dept);

        $this->withHeaders($this->headersFor($admin))
             ->postJson('/api/v1/admin/submissions/bulk-status', [
                 'status' => 'approved',
             ])->assertStatus(422)
               ->assertJsonValidationErrors(['reference_nos']);
    }
}
