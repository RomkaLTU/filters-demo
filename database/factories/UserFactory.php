<?php

namespace Database\Factories;

use App\Enums\Country;
use App\Enums\Status;
use App\Models\User;
use DateTimeInterface;
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => Status::Active,
            'country' => fake()->randomElement(Country::cases())->value,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the user is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Status::Active]);
    }

    /**
     * Indicate that the user is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Status::Pending]);
    }

    /**
     * Indicate that the user is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Status::Suspended]);
    }

    /**
     * Indicate that the user is deactivated.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Status::Deactivated]);
    }

    /**
     * Set the user's country (an ISO-3166 alpha-2 code, or null for none).
     */
    public function country(?string $code): static
    {
        return $this->state(fn (array $attributes) => ['country' => $code]);
    }

    /**
     * Place the user's created_at within a range, for date-filter fixtures.
     */
    public function createdBetween(DateTimeInterface|string $from, DateTimeInterface|string $to): static
    {
        return $this->state(function (array $attributes) use ($from, $to) {
            $created = fake()->dateTimeBetween($from, $to);

            return ['created_at' => $created, 'updated_at' => $created];
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
