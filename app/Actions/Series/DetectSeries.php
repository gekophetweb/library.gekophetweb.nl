<?php

namespace App\Actions\Series;

use App\Enums\CategoryKind;
use App\Models\CollectionObject;
use App\Models\User;
use App\Services\Series\SeriesLookupManager;
use App\Support\Isbn;
use App\Support\Series\SeriesMatch;
use App\Support\Series\SeriesResolution;
use App\Support\Series\VolumeNumber;

/**
 * Series detection for an item added without the user watching (batch scans, wishlist
 * adds): a high-confidence match is applied, anything less is stored as a suggestion the
 * item page offers to accept (plan series-detection-from-isbn §7 decision 1).
 */
class DetectSeries
{
    public function __construct(private readonly SeriesLookupManager $series, private readonly LinkSeries $link) {}

    public function handle(CollectionObject $object, string $language): SeriesResolution
    {
        $resolution = $this->resolve($object, $language);
        $best = $resolution->best();

        if ($best === null) {
            return $resolution;
        }

        if ($resolution->isConfident()) {
            $this->link->toReference($object, $best->series, $best->volume);
        } else {
            $object->update(['series_suggestion' => self::suggestion($best)]);
        }

        return $resolution;
    }

    /**
     * Resolution only, for the single scan's pre-filled form. Empty for anything but a book
     * with an ISBN, or one already in a series. $user (the item's owner when an item is
     * given) supplies the keys for Google Books and Hardcover.
     */
    public function resolve(CollectionObject|string $objectOrBarcode, string $language, ?string $title = null, ?User $user = null): SeriesResolution
    {
        if ($objectOrBarcode instanceof CollectionObject) {
            if ($objectOrBarcode->serie_id !== null || $objectOrBarcode->category->topLevel()->kind !== CategoryKind::Books) {
                return new SeriesResolution;
            }

            [$barcode, $title, $user] = [$objectOrBarcode->barcode, $objectOrBarcode->name, $objectOrBarcode->user];
        } else {
            $barcode = $objectOrBarcode;
        }

        $isbn = Isbn::tryFrom($barcode);

        return $isbn === null ? new SeriesResolution : $this->series->resolve($isbn, $language, $title, $user);
    }

    /** @return array{reference: array<string, string|null>, volume: ?string, source: string, confidence: string} */
    public static function suggestion(SeriesMatch $match): array
    {
        return [
            'reference' => $match->series->toArray(),
            'volume' => $match->volume ? (string) $match->volume : null,
            'source' => $match->source,
            'confidence' => $match->confidence->value,
        ];
    }

    /** @param  array{volume?: ?string}  $suggestion */
    public static function suggestedVolume(array $suggestion): ?VolumeNumber
    {
        return VolumeNumber::parse($suggestion['volume'] ?? null);
    }
}
