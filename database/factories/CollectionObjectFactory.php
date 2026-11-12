<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectionObject>
 */
class CollectionObjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'sub_category_id' => null,
            'barcode' => fake()->unique()->ean13(),
            'name' => $title,
            'name_romanized' => $title,
            'name_english' => null,
            'publisher_id' => Publisher::factory(),
            'release_date' => fake()->date(),
        ];
    }

    /** A manga: category Books, sub-category Manga. */
    public function book(): static
    {
        return $this->state(function (array $attributes) {
            $books = Category::factory()->create(['kind' => CategoryKind::Books]);

            return [
                'category_id' => $books->id,
                'sub_category_id' => Category::factory()->childOf($books)->create()->id,
            ];
        });
    }
}
