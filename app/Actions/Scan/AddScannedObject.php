<?php

namespace App\Actions\Scan;

use App\Enums\CategoryKind;
use App\Models\Artist;
use App\Models\CollectionObject;
use App\Models\Publisher;
use App\Models\User;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Scan\ScanSetup;
use App\Support\Series\TitleVolumeParser;
use Illuminate\Support\Facades\DB;

/**
 * Adds a scanned item to a user's collection from a catalogue result (or details typed by
 * hand). Idempotent: a barcode the user already has returns the existing item.
 */
class AddScannedObject
{
    public function __construct(private readonly TitleVolumeParser $titles) {}

    public function handle(User $user, ScanSetup $setup, CatalogueResult $result): CollectionObject
    {
        $existing = CollectionObject::query()->ownedBy($user)->where('barcode', $result->barcode)->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $setup, $result) {
            $object = new CollectionObject([
                'category_id' => $setup->category->id,
                'sub_category_id' => $setup->subCategory?->id,
                'barcode' => $result->barcode,
                'name' => $result->name,
                // Required (§7). The catalogues rarely give a romanization; the original
                // title stands in until the user edits it.
                'name_romanized' => $result->nameRomanized ?? $result->name,
                'name_english' => $result->nameEnglish,
                'publisher_id' => $result->publisher ? $this->publisher($result->publisher)->id : null,
                'release_date' => self::date($result->releaseDate),
            ]);
            $object->user()->associate($user)->save();
            $object->languages()->attach($setup->language);

            $artist = $result->creator ? $this->artist($result->creator) : null;

            match ($setup->category->kind) {
                CategoryKind::Books => $object->bookDetail()->create([
                    'artist_id' => $artist?->id,
                    'volume' => ($volume = $this->titles->parse($result->name)?->volume) ? (string) $volume : null,
                    'volume_sort' => $volume?->toFloat(),
                ]),
                CategoryKind::Music => $object->musicDetail()->create(['artist_id' => $artist?->id]),
                CategoryKind::Games => $object->gameDetail()->create([]),
            };

            return $object;
        });
    }

    private function publisher(string $name): Publisher
    {
        return Publisher::query()->firstOrCreate(['name' => $name], ['name_romanized' => $name]);
    }

    private function artist(string $name): Artist
    {
        return Artist::query()->firstOrCreate(['name' => $name], ['name_romanized' => $name]);
    }

    /** "1990" / "1990-12" / "1990-12-05" → a date on the first of the unknown parts. */
    private static function date(?string $date): ?string
    {
        if ($date === null || ! preg_match('/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?/', $date, $m)) {
            return null;
        }

        return sprintf('%s-%s-%s', $m[1], ($m[2] ?? '') ?: '01', ($m[3] ?? '') ?: '01');
    }
}
