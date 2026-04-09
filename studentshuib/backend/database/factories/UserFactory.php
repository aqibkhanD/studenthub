<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Default state — creates a student user.
     */
    public function definition(): array
    {
        static $seq = 1000;
        $seq++;

        return [
            'student_id'        => "DIU-{$seq}",
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'phone'             => '017' . fake()->numerify('########'),
            'password'          => 'password',   // hashed by model cast
            'role'              => 'student',
            'department_id'     => null,
            'program'           => fake()->randomElement(['CSE', 'EEE', 'BBA', 'MBA', 'Law']),
            'batch'             => fake()->randomElement(['Fall 2022', 'Spring 2023', 'Fall 2023']),
            'semester'          => fake()->randomElement(['1st', '3rd', '5th', '7th']),
            'is_active'         => true,
            'email_verified_at' => now(),
            'remember_token'    => Str::random(10),
        ];
    }

    // ------------------------------------------------------------------
    // Role states
    // ------------------------------------------------------------------

    /**
     * Admin scoped to a specific department.
     */
    public function admin(Department $dept): static
    {
        return $this->state(fn () => [
            'role'          => 'admin',
            'department_id' => $dept->id,
            'student_id'    => null,
            'program'       => null,
            'batch'         => null,
            'semester'      => null,
        ]);
    }

    /**
     * Department head — same access as admin but with dept_head role.
     */
    public function deptHead(Department $dept): static
    {
        return $this->state(fn () => [
            'role'          => 'dept_head',
            'department_id' => $dept->id,
            'student_id'    => null,
            'program'       => null,
            'batch'         => null,
            'semester'      => null,
        ]);
    }

    /**
     * Super-admin — no department scope, full access.
     */
    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role'          => 'super_admin',
            'department_id' => null,
            'student_id'    => null,
            'program'       => null,
            'batch'         => null,
            'semester'      => null,
        ]);
    }

    /**
     * Management — read-only analytics / digest access.
     */
    public function management(): static
    {
        return $this->state(fn () => [
            'role'          => 'management',
            'department_id' => null,
            'student_id'    => null,
            'program'       => null,
            'batch'         => null,
            'semester'      => null,
        ]);
    }

    // ------------------------------------------------------------------
    // Modifier states
    // ------------------------------------------------------------------

    /**
     * Mark the user as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * User with a known, predictable password (for login tests).
     */
    public function withPassword(string $password): static
    {
        return $this->state(fn () => ['password' => $password]);
    }
}
