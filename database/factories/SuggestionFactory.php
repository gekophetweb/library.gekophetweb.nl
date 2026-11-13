<?php

namespace Database\Factories;

use App\Enums\SuggestionStatus;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suggestion>
 */
class SuggestionFactory extends Factory
{
    /**
     * A new suggestion without a reply.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->sentence(12),
            'status' => SuggestionStatus::New,
            'admin_reply' => null,
        ];
    }
}
