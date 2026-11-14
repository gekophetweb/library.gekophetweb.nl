<?php

use App\Models\CollectionObject;
use App\Models\Language;
use Database\Seeders\CatalogueSeeder;

it('seeds Japanese, English, Dutch and Korean only', function () {
    $this->seed(CatalogueSeeder::class);

    expect(Language::query()->orderBy('code')->pluck('code')->all())->toBe(['en', 'ja', 'ko', 'nl']);
});

it('removes Chinese, French and German from existing installs unless something uses them', function () {
    foreach (['zh' => 'Chinese', 'fr' => 'French', 'de' => 'German', 'ja' => 'Japanese'] as $code => $name) {
        Language::query()->create(['code' => $code, 'name' => $name]);
    }
    CollectionObject::factory()->create()->languages()->attach(Language::query()->where('code', 'fr')->sole());

    (require database_path('migrations/2026_11_14_000000_remove_unused_default_languages.php'))->up();

    expect(Language::query()->orderBy('code')->pluck('code')->all())->toBe(['fr', 'ja']);
});
