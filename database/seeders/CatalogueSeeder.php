<?php

namespace Database\Seeders;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\Language;
use App\Models\Platform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** The starting taxonomy (guidelines §3.2). Safe to run again: it only adds what is missing. */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            'Books' => [CategoryKind::Books, ['Manga', 'Novels']],
            'Music' => [CategoryKind::Music, ['CD', 'Vinyl', 'Tape', 'Minidisc']],
            'Games' => [CategoryKind::Games, []],
        ];

        $sort = 0;
        foreach ($tree as $name => [$kind, $children]) {
            $parent = Category::query()->firstOrCreate(
                ['parent_id' => null, 'slug' => Str::slug($name)],
                ['kind' => $kind, 'name' => $name, 'sort' => $sort++],
            );

            foreach ($children as $index => $child) {
                Category::query()->firstOrCreate(
                    ['parent_id' => $parent->id, 'slug' => Str::slug($child)],
                    ['kind' => $kind, 'name' => $child, 'sort' => $index],
                );
            }
        }

        foreach (['ja' => 'Japanese', 'en' => 'English', 'nl' => 'Dutch', 'de' => 'German', 'fr' => 'French', 'ko' => 'Korean', 'zh' => 'Chinese'] as $code => $name) {
            Language::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach (['PC', 'PlayStation 5', 'PlayStation 4', 'Nintendo Switch', 'Xbox Series X|S'] as $platform) {
            Platform::query()->firstOrCreate(['name' => $platform]);
        }
    }
}
