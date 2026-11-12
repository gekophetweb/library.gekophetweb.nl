<?php

namespace Database\Factories;

use App\Enums\CategoryKind;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'parent_id' => null,
            'kind' => CategoryKind::Books,
            'name' => $name,
            'slug' => Str::slug($name),
            'sort' => 0,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn (array $attributes) => ['parent_id' => $parent->id, 'kind' => $parent->kind]);
    }
}
