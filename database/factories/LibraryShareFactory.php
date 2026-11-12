<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\LibraryShare;
use App\Models\Series;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryShare>
 */
class LibraryShareFactory extends Factory
{
    /**
     * Whole library, never expiring.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => null,
            'expires_at' => null,
        ];
    }

    public function forCategory(Category $category): static
    {
        return $this->state(['shareable_type' => $category->getMorphClass(), 'shareable_id' => $category->id]);
    }

    public function forSeries(Series $series): static
    {
        return $this->state(['shareable_type' => $series->getMorphClass(), 'shareable_id' => $series->id, 'user_id' => $series->user_id]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
