<?php

namespace Database\Factories\Admin;

use App\Enums\Admin\AuditEvent;
use App\Models\Admin\AuditLog;
use App\Models\Admin\Designation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 *
 * Audit rows are never created by hand in the application — the package and
 * `Admin\AuditRecorder` are the only writers. This factory exists for the list
 * surface's tests, which need rows without exercising a write path to make them.
 *
 * A test that means to prove *auditing* should therefore not use this: change a
 * real model and assert on what appeared. This is for proving the list.
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The subject defaults to a `Designation` because it is the cheapest audited
     * model to make — no buyer, no parent, no upload.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_type' => 'user',
            'user_id' => null,
            'actor_name' => fake()->name(),
            'actor_employee_id' => (string) fake()->unique()->numberBetween(10000, 99999),
            'event' => AuditEvent::Updated->value,
            'auditable_type' => 'designation',
            'auditable_id' => Designation::factory(),
            'old_values' => ['name' => fake()->jobTitle()],
            'new_values' => ['name' => fake()->jobTitle()],
            'url' => fake()->url(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'tags' => null,
        ];
    }

    /**
     * Indicate that the audit names no record — an authentication event.
     */
    public function unattached(): static
    {
        return $this->state(fn (array $attributes): array => [
            'auditable_type' => null,
            'auditable_id' => null,
            'old_values' => [],
            'new_values' => [],
        ]);
    }

    /**
     * Indicate the audit belongs to the given buyer, as `generateTags()` writes it.
     */
    public function forBuyer(int $buyerId): static
    {
        return $this->state(fn (array $attributes): array => [
            'tags' => 'buyer:'.$buyerId,
        ]);
    }
}
