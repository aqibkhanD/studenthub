<?php

namespace Tests;

use App\Models\Department;
use App\Models\FormType;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // ---- Auth helpers --------------------------------------------------------

    /** Create a student user (default role). */
    protected function makeStudent(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    /** Create a dept-scoped admin user. */
    protected function makeAdmin(Department $dept, array $attrs = []): User
    {
        return User::factory()->admin($dept)->create($attrs);
    }

    /** Create a super-admin user. */
    protected function makeSuperAdmin(array $attrs = []): User
    {
        return User::factory()->superAdmin()->create($attrs);
    }

    /** Create a management user. */
    protected function makeManagement(array $attrs = []): User
    {
        return User::factory()->management()->create($attrs);
    }

    /** Return headers with a Sanctum bearer token for the given user. */
    protected function headersFor(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer {$token}"];
    }

    // ---- Entity helpers ------------------------------------------------------

    /** Create a department with sensible defaults. */
    protected function makeDepartment(array $attrs = []): Department
    {
        static $n = 0;
        $n++;
        return Department::create(array_merge([
            'name'      => "Test Department {$n}",
            'slug'      => "test-dept-{$n}",
            'code'      => strtoupper("TD{$n}"),
            'sla_hours' => 48,
            'is_active' => true,
        ], $attrs));
    }

    /** Create an active form type for the given department. */
    protected function makeFormType(Department $dept, array $attrs = []): FormType
    {
        static $n = 0;
        $n++;
        return FormType::create(array_merge([
            'name'               => "Test Form Type {$n}",
            'slug'               => "test-form-type-{$n}",
            'category'           => 'academic_certification',
            'department_id'      => $dept->id,
            'is_active'          => true,
            'requires_documents' => false,
            'allow_anonymous'    => false,
            'sla_hours'          => 48,
        ], $attrs));
    }

    /** Create a submission directly (bypasses the service — use when testing admin behaviour). */
    protected function makeSubmission(User $student, FormType $formType, string $status = 'submitted', array $attrs = []): Submission
    {
        static $seq = 1000;
        $seq++;
        return Submission::create(array_merge([
            'reference_no'  => "DIU-TEST-{$seq}",
            'form_type_id'  => $formType->id,
            'student_id'    => $student->id,
            'department_id' => $formType->department_id,
            'status'        => $status,
            'form_data'     => ['description' => 'Test submission content.'],
            'submitted_at'  => $status !== Submission::STATUS_DRAFT ? now() : null,
            'sla_deadline'  => $status !== Submission::STATUS_DRAFT ? now()->addHours(48) : null,
            'is_anonymous'  => false,
        ], $attrs));
    }

    /** POST to the student submission endpoint and return the created reference number. */
    protected function submitForm(User $student, FormType $formType, array $extra = []): string
    {
        $response = $this->withHeaders($this->headersFor($student))
            ->postJson('/api/v1/student/submissions', array_merge([
                'form_type_id' => $formType->id,
                'form_data'    => ['description' => 'Integration test submission.'],
                'submit'       => true,
            ], $extra));

        $response->assertStatus(201);
        return $response->json('submission.reference_no');
    }
}
