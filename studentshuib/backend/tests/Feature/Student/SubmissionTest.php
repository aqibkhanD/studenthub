<?php

namespace Tests\Feature\Student;

use Tests\TestCase;

class SubmissionTest extends TestCase
{
    // ================================================================
    // Form type catalogue
    // ================================================================

    public function test_student_can_list_active_form_types(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);

        $this->withHeaders($this->headersFor($student))
             ->getJson('/api/v1/student/form-types')
             ->assertOk()
             ->assertJsonFragment(['id' => $formType->id]);
    }

    public function test_inactive_form_type_not_listed_for_student(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $inactive = $this->makeFormType($dept, ['is_active' => false]);

        $response = $this->withHeaders($this->headersFor($student))
                         ->getJson('/api/v1/student/form-types')
                         ->assertOk();

        $ids = collect($response->json('form_types'))->pluck('id');
        $this->assertNotContains($inactive->id, $ids);
    }

    // ================================================================
    // Submit a form
    // ================================================================

    public function test_student_can_submit_a_form(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);

        $this->withHeaders($this->headersFor($student))
             ->postJson('/api/v1/student/submissions', [
                 'form_type_id' => $formType->id,
                 'form_data'    => ['description' => 'Please issue my bonafide certificate.'],
                 'submit'       => true,
             ])->assertStatus(201)
               ->assertJsonStructure(['submission' => ['reference_no', 'status']]);
    }

    public function test_submission_saved_as_draft_when_submit_false(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);

        $response = $this->withHeaders($this->headersFor($student))
                         ->postJson('/api/v1/student/submissions', [
                             'form_type_id' => $formType->id,
                             'form_data'    => ['description' => 'Draft content.'],
                             'submit'       => false,
                         ])->assertStatus(201);

        $this->assertEquals('draft', $response->json('submission.status'));
    }

    public function test_submission_requires_form_type_id(): void
    {
        $student = $this->makeStudent();

        $this->withHeaders($this->headersFor($student))
             ->postJson('/api/v1/student/submissions', [
                 'form_data' => ['description' => 'Missing form_type_id.'],
                 'submit'    => true,
             ])->assertStatus(422)
               ->assertJsonValidationErrors(['form_type_id']);
    }

    // ================================================================
    // View own submissions
    // ================================================================

    public function test_student_can_view_own_submission_list(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType);

        $response = $this->withHeaders($this->headersFor($student))
                         ->getJson('/api/v1/student/submissions')
                         ->assertOk();

        $refs = collect($response->json('submissions.data'))->pluck('reference_no');
        $this->assertContains($sub->reference_no, $refs);
    }

    public function test_student_cannot_view_another_students_submission(): void
    {
        $dept      = $this->makeDepartment();
        $student1  = $this->makeStudent();
        $student2  = $this->makeStudent();
        $formType  = $this->makeFormType($dept);
        $sub       = $this->makeSubmission($student1, $formType);

        $this->withHeaders($this->headersFor($student2))
             ->getJson("/api/v1/student/submissions/{$sub->reference_no}")
             ->assertStatus(403);
    }

    public function test_student_can_view_own_submission_detail(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType);

        $this->withHeaders($this->headersFor($student))
             ->getJson("/api/v1/student/submissions/{$sub->reference_no}")
             ->assertOk()
             ->assertJsonPath('submission.reference_no', $sub->reference_no);
    }

    // ================================================================
    // Comments
    // ================================================================

    public function test_student_can_add_comment_to_own_submission(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType);

        $this->withHeaders($this->headersFor($student))
             ->postJson("/api/v1/student/submissions/{$sub->reference_no}/comments", [
                 'body' => 'Could you please expedite this?',
             ])->assertStatus(201)
               ->assertJsonPath('comment.body', 'Could you please expedite this?');
    }

    // ================================================================
    // Cancel draft
    // ================================================================

    public function test_student_can_cancel_a_draft(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'draft');

        $this->withHeaders($this->headersFor($student))
             ->deleteJson("/api/v1/student/submissions/{$sub->reference_no}")
             ->assertOk();
    }

    public function test_student_cannot_cancel_a_submitted_form(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'submitted');

        $this->withHeaders($this->headersFor($student))
             ->deleteJson("/api/v1/student/submissions/{$sub->reference_no}")
             ->assertStatus(422);
    }

    // ================================================================
    // Resubmit returned form
    // ================================================================

    public function test_student_can_resubmit_a_returned_form(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);
        $sub      = $this->makeSubmission($student, $formType, 'returned');

        $this->withHeaders($this->headersFor($student))
             ->postJson("/api/v1/student/submissions/{$sub->reference_no}/resubmit", [
                 'form_data' => ['description' => 'Updated content after return.'],
             ])->assertOk()
               ->assertJsonPath('submission.status', 'submitted');
    }
}
