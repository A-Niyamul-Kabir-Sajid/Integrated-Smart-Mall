<?php

namespace Database\Factories;

use App\Modules\AccountsAccess\Enums\AccountStatus;
use App\Modules\AccountsAccess\Models\User;
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
            'display_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'password_hash' => static::$password ??= Hash::make('password'),
            'status' => AccountStatus::Active,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
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
     * An account that signs in with a phone number instead of an email.
     */
    public function phoneOnly(string $phone = '+8801712345678'): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'email_verified_at' => null,
            'phone' => $phone,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AccountStatus::Suspended,
        ]);
    }

    public function anonymized(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => 'Anonymised user',
            'email' => null,
            'phone' => null,
            'status' => AccountStatus::Anonymized,
        ]);
    }
}
