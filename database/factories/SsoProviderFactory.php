<?php

namespace Database\Factories;

use App\Enums\SsoDriver;
use App\Models\SsoProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SsoProvider>
 */
class SsoProviderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver' => SsoDriver::Google,
            'name' => fake()->unique()->company(),
            'client_id' => fake()->uuid(),
            'client_secret' => fake()->sha256(),
            'settings' => [],
            'enabled' => true,
            'allow_registration' => false,
            'sort' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }

    public function oidc(string $issuer = 'https://auth.example.com/'): static
    {
        return $this->state(fn (array $attributes) => [
            'driver' => SsoDriver::Oidc,
            'settings' => ['issuer' => $issuer],
        ]);
    }
}
