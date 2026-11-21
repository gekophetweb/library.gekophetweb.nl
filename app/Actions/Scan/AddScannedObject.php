<?php

namespace App\Actions\Scan;

use App\Enums\CategoryKind;
use App\Enums\OwnershipStatus;
use App\Models\Artist;
use App\Models\CollectionObject;
use App\Models\Publisher;
use App\Models\User;
use App\Services\Romanization\NameRomanizer;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Romanization\Hepburn;
use App\Support\Scan\ScanSetup;
use App\Support\Series\TitleVolumeParser;
use Illuminate\Support\Facades\DB;

/**
 * Adds a scanned item to a user's collection (owned or wishlist) from a catalogue result
 * or details typed by hand. Idempotent: a barcode the user already has returns the
 * existing item — promoted to owned when it was wanted and this is an Own scan. An owned
 * item is never demoted by a Wishlist scan.
 */
class AddScannedObject
{
    public function __construct(private readonly TitleVolumeParser $titles, private readonly NameRomanizer $romanizer) {}

    public function handle(User $user, ScanSetup $setup, CatalogueResult $result): CollectionObject
    {
        $existing = CollectionObject::query()->ownedBy($user)->where('barcode', $result->barcode)->first();

        if ($existing !== null) {
            return self::promoteIfAcquired($existing, $setup->ownership);
        }

        // Before the transaction: a new publisher or artist may need a Wikidata call.
        $language = $setup->language->code;
        $publisher = $result->publisher ? $this->publisher($result->publisher, $language) : null;
        $artist = $result->creator ? $this->artist($result->creator, $result->creatorReading, $language) : null;
        // Required (§7). Japanese and Korean titles are romanized from the catalogue's kana
        // reading or their kana / Hangul; otherwise the original title stands in until edited.
        $romanized = $result->nameRomanized === null ? $this->romanizer->title($result->name, $result->nameReading) : null;

        return DB::transaction(function () use ($user, $setup, $result, $publisher, $artist, $romanized) {
            $object = new CollectionObject([
                'category_id' => $setup->category->id,
                'sub_category_id' => $setup->subCategory?->id,
                'barcode' => $result->barcode,
                'ownership' => $setup->ownership,
                'name' => $result->name,
                'name_romanized' => $result->nameRomanized ?? $romanized ?? $result->name,
                'name_romanized_auto' => $romanized !== null,
                'name_english' => $result->nameEnglish,
                'publisher_id' => $publisher?->id,
                'release_date' => self::normalizeDate($result->releaseDate),
            ]);
            $object->user()->associate($user)->save();
            $object->languages()->attach($setup->language);

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

    /**
     * Scanning a wanted item in Own mode is the acquisition: flip it to owned. Never the
     * other way round. Check `wasChanged('ownership')` on the result to tell the user.
     */
    public static function promoteIfAcquired(CollectionObject $existing, OwnershipStatus $scannedAs): CollectionObject
    {
        if ($scannedAs === OwnershipStatus::Owned && ! $existing->isOwned()) {
            $existing->update(['ownership' => OwnershipStatus::Owned]);
        }

        return $existing;
    }

    /** Publishers are shared: a new one is romanized once (its kana / Hangul, or Wikidata). */
    private function publisher(string $name, string $language): Publisher
    {
        $existing = Publisher::query()->where('name', $name)->first();

        if ($existing !== null) {
            return $existing;
        }

        $romanized = $this->romanizer->publisher($name, $language);

        return Publisher::query()->create(['name' => $name, 'name_romanized' => $romanized ?? $name, 'name_romanized_auto' => $romanized !== null]);
    }

    /**
     * Artists are shared: a new one is romanized from the catalogue's reading (or Wikidata);
     * an existing one still without a romanization gets one when this scan brings a reading.
     */
    private function artist(string $name, ?string $reading, string $language): Artist
    {
        $existing = Artist::query()->where('name', $name)->first();

        if ($existing !== null) {
            // Only from a reading: no Wikidata call for an artist seen before. A Latin name
            // ("One") romanizes to null and keeps its name as it is.
            if ($reading !== null && Hepburn::fromKana($reading) !== null && $existing->hasPlaceholderRomanization()
                && ($romanized = $this->romanizer->person($name, $reading, $language)) !== null) {
                $existing->update(['name_romanized' => $romanized, 'name_romanized_auto' => true]);
            }

            return $existing;
        }

        $romanized = $this->romanizer->person($name, $reading, $language);

        return Artist::query()->create(['name' => $name, 'name_romanized' => $romanized ?? $name, 'name_romanized_auto' => $romanized !== null]);
    }

    /** "1990" / "1990-12" / "1990-12-05" → a date on the first of the unknown parts. */
    public static function normalizeDate(?string $date): ?string
    {
        if ($date === null || ! preg_match('/^(\d{4})(?:-(\d{2}))?(?:-(\d{2}))?/', $date, $m)) {
            return null;
        }

        return sprintf('%s-%s-%s', $m[1], ($m[2] ?? '') ?: '01', ($m[3] ?? '') ?: '01');
    }
}
