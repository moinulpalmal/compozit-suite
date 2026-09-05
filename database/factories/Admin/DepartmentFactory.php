<?php

namespace Database\Factories\Admin;

use App\Enums\RecordStatus;
use App\Models\Admin\Buyer;
use App\Models\Admin\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * Define the model's default state.
     *
     * `name` and `code` are unique **per buyer**, not globally, so drawing them
     * uniquely here is stricter than the database requires — deliberately. A
     * factory that collides on the thirtieth row is a test that fails for a
     * reason nobody wants to debug, and tests that need the same name under two
     * buyers state it explicitly rather than relying on the draw.
     *
     * Values are upper-cased because that is how a buyer writes them on a BQS
     * (`GIRLSWEAR`, `BOYSWEAR`), and a factory that does not look like the real
     * data hides formatting bugs.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => Buyer::factory(),
            'name' => strtoupper(fake()->unique()->words(2, true)),
            'code' => strtoupper(fake()->unique()->bothify('??##')),
            'status' => RecordStatus::Active,
        ];
    }

    /**
     * A department retired from the pickers, its rows left in place.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RecordStatus::Inactive,
        ]);
    }

    /**
     * A department with no code yet — legal, because the unique index permits
     * repeated NULLs on both MySQL and SQLite.
     */
    public function withoutCode(): static
    {
        return $this->state(fn (array $attributes): array => [
            'code' => null,
        ]);
    }
}
