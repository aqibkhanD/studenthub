<?php

namespace Tests\Feature\Super;

use App\Models\FormField;
use Tests\TestCase;

class FormFieldTest extends TestCase
{
    // ================================================================
    // Index
    // ================================================================

    public function test_super_admin_can_list_fields_for_form_type(): void
    {
        $dept     = $this->makeDepartment();
        $super    = $this->makeSuperAdmin();
        $formType = $this->makeFormType($dept);

        FormField::create([
            'form_type_id'  => $formType->id,
            'label'         => 'Full Name',
            'field_key'     => 'full_name',
            'field_type'    => 'text',
            'is_required'   => true,
            'sort_order'    => 0,
        ]);

        $this->withHeaders($this->headersFor($super))
             ->getJson("/api/v1/super/form-types/{$formType->id}/fields")
             ->assertOk()
             ->assertJsonFragment(['field_key' => 'full_name']);
    }

    // ================================================================
    // Create
    // ================================================================

    public function test_super_admin_can_create_a_field(): void
    {
        $dept     = $this->makeDepartment();
        $super    = $this->makeSuperAdmin();
        $formType = $this->makeFormType($dept);

        $this->withHeaders($this->headersFor($super))
             ->postJson("/api/v1/super/form-types/{$formType->id}/fields", [
                 'label'       => 'Student Phone',
                 'field_key'   => 'student_phone',
                 'field_type'  => 'phone',
                 'is_required' => true,
             ])->assertStatus(201)
               ->assertJsonPath('field.field_key', 'student_phone');
    }

    public function test_field_key_must_be_unique_within_form_type(): void
    {
        $dept     = $this->makeDepartment();
        $super    = $this->makeSuperAdmin();
        $formType = $this->makeFormType($dept);

        FormField::create([
            'form_type_id' => $formType->id,
            'label'        => 'Email',
            'field_key'    => 'email',
            'field_type'   => 'email',
            'is_required'  => true,
            'sort_order'   => 0,
        ]);

        $this->withHeaders($this->headersFor($super))
             ->postJson("/api/v1/super/form-types/{$formType->id}/fields", [
                 'label'       => 'Email Address',
                 'field_key'   => 'email',  // duplicate key
                 'field_type'  => 'email',
                 'is_required' => false,
             ])->assertStatus(422)
               ->assertJsonValidationErrors(['field_key']);
    }

    public function test_same_field_key_allowed_in_different_form_types(): void
    {
        $dept      = $this->makeDepartment();
        $super     = $this->makeSuperAdmin();
        $formType1 = $this->makeFormType($dept);
        $formType2 = $this->makeFormType($dept);

        FormField::create([
            'form_type_id' => $formType1->id,
            'label'        => 'Email',
            'field_key'    => 'email',
            'field_type'   => 'email',
            'is_required'  => true,
            'sort_order'   => 0,
        ]);

        // Same key but different form_type — should succeed
        $this->withHeaders($this->headersFor($super))
             ->postJson("/api/v1/super/form-types/{$formType2->id}/fields", [
                 'label'       => 'Email',
                 'field_key'   => 'email',
                 'field_type'  => 'email',
                 'is_required' => true,
             ])->assertStatus(201);
    }

    // ================================================================
    // Update
    // ================================================================

    public function test_super_admin_can_update_a_field(): void
    {
        $dept     = $this->makeDepartment();
        $super    = $this->makeSuperAdmin();
        $formType = $this->makeFormType($dept);

        $field = FormField::create([
            'form_type_id' => $formType->id,
            'label'        => 'Old Label',
            'field_key'    => 'old_label',
            'field_type'   => 'text',
            'is_required'  => false,
            'sort_order'   => 0,
        ]);

        $this->withHeaders($this->headersFor($super))
             ->putJson("/api/v1/super/form-types/{$formType->id}/fields/{$field->id}", [
                 'label'       => 'New Label',
                 'field_key'   => 'new_label',
                 'field_type'  => 'text',
                 'is_required' => true,
             ])->assertOk()
               ->assertJsonPath('field.label', 'New Label');
    }

    // ================================================================
    // Delete
    // ================================================================

    public function test_super_admin_can_delete_a_field(): void
    {
        $dept     = $this->makeDepartment();
        $super    = $this->makeSuperAdmin();
        $formType = $this->makeFormType($dept);

        $field = FormField::create([
            'form_type_id' => $formType->id,
            'label'        => 'To Delete',
            'field_key'    => 'to_delete',
            'field_type'   => 'text',
            'is_required'  => false,
            'sort_order'   => 0,
        ]);

        $this->withHeaders($this->headersFor($super))
             ->deleteJson("/api/v1/super/form-types/{$formType->id}/fields/{$field->id}")
             ->assertOk();

        $this->assertDatabaseMissing('form_fields', ['id' => $field->id]);
    }

    // ================================================================
    // Reorder
    // ================================================================

    public function test_super_admin_can_reorder_fields(): void
    {
        $dept     = $this->makeDepartment();
        $super    = $this->makeSuperAdmin();
        $formType = $this->makeFormType($dept);

        $fieldA = FormField::create([
            'form_type_id' => $formType->id, 'label' => 'A', 'field_key' => 'a',
            'field_type' => 'text', 'is_required' => false, 'sort_order' => 0,
        ]);
        $fieldB = FormField::create([
            'form_type_id' => $formType->id, 'label' => 'B', 'field_key' => 'b',
            'field_type' => 'text', 'is_required' => false, 'sort_order' => 1,
        ]);

        // Swap order: B first, A second
        $this->withHeaders($this->headersFor($super))
             ->postJson("/api/v1/super/form-types/{$formType->id}/fields/reorder", [
                 'order' => [$fieldB->id, $fieldA->id],
             ])->assertOk();

        $this->assertEquals(0, $fieldB->fresh()->sort_order);
        $this->assertEquals(1, $fieldA->fresh()->sort_order);
    }

    // ================================================================
    // Access control
    // ================================================================

    public function test_regular_admin_cannot_manage_form_fields(): void
    {
        $dept     = $this->makeDepartment();
        $admin    = $this->makeAdmin($dept);
        $formType = $this->makeFormType($dept);

        $this->withHeaders($this->headersFor($admin))
             ->postJson("/api/v1/super/form-types/{$formType->id}/fields", [
                 'label'       => 'Test Field',
                 'field_key'   => 'test_field',
                 'field_type'  => 'text',
                 'is_required' => false,
             ])->assertStatus(403);
    }

    public function test_student_cannot_access_form_field_endpoints(): void
    {
        $dept     = $this->makeDepartment();
        $student  = $this->makeStudent();
        $formType = $this->makeFormType($dept);

        $this->withHeaders($this->headersFor($student))
             ->getJson("/api/v1/super/form-types/{$formType->id}/fields")
             ->assertStatus(403);
    }
}
