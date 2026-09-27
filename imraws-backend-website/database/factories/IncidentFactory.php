<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'     => User::factory()->customer(),
            'description'     => fake()->sentence(12),
            'location'        => fake()->city(),
            'latitude'        => fake()->latitude(14.5, 14.8),
            'longitude'       => fake()->longitude(120.8, 121.1),
            'category'        => 'Metering',
            'severity'        => 'Medium',
            'composite_score' => 0.5,
            'status'          => Incident::STATUS_OPEN,
            'submitted_at'    => now(),
        ];
    }

    public function forCustomer(User $customer): static
    {
        return $this->state(fn () => ['customer_id' => $customer->id]);
    }

    /** @param  string  $category  must match a key in VisibilityScope's map. */
    public function categorisedAs(string $category): static
    {
        return $this->state(fn () => ['category' => $category]);
    }

    /** Unrouted work, which only an engineer's department may pick up. */
    public function unclassified(): static
    {
        return $this->state(fn () => [
            'category' => null,
            'severity' => null,
        ]);
    }
}
