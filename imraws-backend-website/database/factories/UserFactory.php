<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name'         => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'role'              => User::ROLE_CUSTOMER,
            'is_active'         => true,
            'is_team_leader'    => false,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_CUSTOMER]);
    }

    public function administrator(): static
    {
        return $this->state(fn () => ['role' => User::ROLE_ADMINISTRATOR]);
    }

    /**
     * @param  string|null  $department  null exercises the "no department on
     *                                   file" branch in VisibilityScope.
     */
    public function engineer(?string $department = null): static
    {
        return $this->state(fn () => [
            'role'             => User::ROLE_ENGINEER,
            'department_team'  => $department,
        ]);
    }

    public function offsiteStaff(?string $department = null): static
    {
        return $this->state(fn () => [
            'role'             => User::ROLE_OFFSITE_STAFF,
            'department_team'  => $department,
            'is_team_leader'   => true,
        ]);
    }
}
