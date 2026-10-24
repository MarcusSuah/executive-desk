<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use App\Support\RolesAndPermissions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as RoleModel;

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
            'timezone' => 'UTC',
            'is_active' => true,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
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
     * Give the user the system administrator role.
     */
    public function administrator(): static
    {
        return $this->withRole(Role::Administrator);
    }

    /**
     * Give the user the executive assistant role.
     */
    public function assistant(): static
    {
        return $this->withRole(Role::Assistant);
    }

    /**
     * Give the user the executive role.
     */
    public function executive(): static
    {
        return $this->withRole(Role::Executive);
    }

    /**
     * Indicate that the account has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Give the user a verified phone number.
     */
    public function withVerifiedPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone' => '+639171234567',
            'phone_verified_at' => now(),
        ]);
    }

    /**
     * Assign a role after the user is created.
     */
    private function withRole(Role $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            if (! RoleModel::query()->where('name', $role->value)->exists()) {
                RolesAndPermissions::sync();
            }

            $user->assignRole($role->value);
        });
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
