<?php

namespace Database\Factories;

use App\Models\Suggestion;
use App\Models\SuggestionComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuggestionComment>
 */
class SuggestionCommentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'suggestion_id' => Suggestion::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(8),
        ];
    }
}
