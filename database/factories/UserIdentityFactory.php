<?php

namespace Database\Factories;

use App\Models\SsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserIdentity>
 */
class UserIdentityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sso_provider_id' => SsoProvider::factory(),
            'subject' => fake()->unique()->uuid(),
            'email' => fake()->safeEmail(),
        ];
    }
}
