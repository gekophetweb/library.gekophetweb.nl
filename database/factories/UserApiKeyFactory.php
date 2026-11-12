<?php

namespace Database\Factories;

use App\Enums\ApiKeyProvider;
use App\Models\User;
use App\Models\UserApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserApiKey>
 */
class UserApiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => ApiKeyProvider::Rakuten,
            'key' => fake()->sha1(),
        ];
    }
}
