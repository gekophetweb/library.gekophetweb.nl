<?php

namespace Database\Factories;

use App\Models\Series;
use App\Models\SeriesVolume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeriesVolume>
 */
class SeriesVolumeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 500);

        return [
            'series_id' => Series::factory(),
            'volume_number' => (string) $number,
            'volume_sort' => $number,
            'edition' => '',
            'isbns' => [],
        ];
    }
}
