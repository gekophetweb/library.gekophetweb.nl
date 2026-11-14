<?php

namespace Database\Factories;

use App\Enums\ApiKeyProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['is_admin' => true]);
    }

    /** A key for every provider, so the user may scan (Rakuten's is "app-123"). */
    public function withApiKeys(): static
    {
        return $this->afterCreating(function (User $user) {
            foreach (ApiKeyProvider::cases() as $provider) {
                $user->apiKeys()->create([
                    'provider' => $provider,
                    'key' => $provider === ApiKeyProvider::Rakuten ? 'app-123' : "{$provider->value}-key",
                    'secret' => $provider->needsSecret() ? 'pk_test' : null,
                ]);
            }
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }
}
