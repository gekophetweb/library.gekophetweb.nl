<?php

namespace App\Actions;

use App\Enums\CategoryKind;
use App\Models\CollectionObject;
use App\Support\Series\VolumeNumber;
use Illuminate\Support\Facades\DB;

/** Applies validated UpdateCollectionObjectRequest data; shared by the web form and the API. */
class UpdateCollectionObject
{
    /** @param  array<string, mixed>  $data */
    public function handle(CollectionObject $object, array $data): CollectionObject
    {
        DB::transaction(function () use ($object, $data) {
            $object->update([
                'name' => $data['name'],
                'name_romanized' => $data['name_romanized'],
                'name_english' => $data['name_english'] ?? null,
                'sub_category_id' => $data['sub_category_id'] ?? null,
                'publisher_id' => $data['publisher_id'] ?? null,
                'release_date' => $data['release_date'] ?? null,
                'serie_id' => $data['serie_id'] ?? null,
                // Never silently demote a wanted item (or promote one) when the field is left out.
                'ownership' => $data['ownership'] ?? $object->ownership,
            ]);

            $object->languages()->sync($data['languages']);
            $object->genres()->sync($data['genres'] ?? []);

            // Category-specific details go to their own table only (§3.3).
            match ($object->category->kind) {
                CategoryKind::Books => $object->bookDetail()->updateOrCreate([], [
                    'artist_id' => $data['artist_id'] ?? null,
                    'volume' => ($volume = VolumeNumber::parse($data['volume'] ?? null)) ? (string) $volume : null,
                    'volume_sort' => $volume?->toFloat(),
                ]),
                CategoryKind::Music => $object->musicDetail()->updateOrCreate([], ['artist_id' => $data['artist_id'] ?? null]),
                CategoryKind::Games => $object->gameDetail()->updateOrCreate([], ['platform_id' => $data['platform_id'] ?? null]),
            };
        });

        return $object->refresh();
    }
}
