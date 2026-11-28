<?php

namespace App\Actions\Series;

use App\Enums\CategoryKind;
use App\Models\CollectionObject;
use App\Models\Series;
use App\Models\SeriesVolume;
use App\Models\User;
use App\Services\Series\SeriesLookupManager;
use App\Support\Isbn;
use App\Support\Series\Confidence;
use App\Support\Series\SeriesMatch;
use App\Support\Series\SeriesReference;
use App\Support\Series\SeriesResolution;
use App\Support\Series\Text;
use App\Support\Series\TitleVolumeParser;
use App\Support\Series\VolumeNumber;

/**
 * Series detection for an item added without the user watching (batch scans, wishlist
 * adds): a high-confidence match is applied, anything less is stored as a suggestion the
 * item page offers to accept (plan series-detection-from-isbn §7 decision 1).
 */
class DetectSeries
{
    /** Source of a match found in the owner's own library rather than a catalogue. */
    public const string SOURCE_LIBRARY = 'library';

    public function __construct(
        private readonly SeriesLookupManager $series,
        private readonly LinkSeries $link,
        private readonly TitleVolumeParser $titleParser,
    ) {}

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
            $languages = $objectOrBarcode->languages->pluck('code')->all();
        } else {
            $barcode = $objectOrBarcode;
            $languages = [$language];
        }

        $isbn = Isbn::tryFrom($barcode);

        if ($isbn === null) {
            return new SeriesResolution;
        }

        if ($user !== null && ($own = $this->fromLibrary($isbn, $title, $user, $languages)) !== null) {
            // A bare title (`Claymore`) says which series but not which volume: the
            // catalogues may still know the number, even when they name another series.
            if ($own->volume === null && ($volume = $this->series->resolve($isbn, $language, $title, $user)->best()?->volume) !== null) {
                $own = new SeriesMatch($own->series, $volume, $own->confidence, $own->source);
            }

            return new SeriesResolution([$own]);
        }

        return $this->series->resolve($isbn, $language, $title, $user);
    }

    /**
     * The owner's own library answers first: a series whose volume list carries this
     * ISBN, else the one series holding their other volumes of the same title (`マギ 3`
     * joins the series of `マギ 1` and `マギ 10`). Catalogues often miss single volumes
     * of a series they know, or name it differently (Hardcover's English title).
     *
     * The title match only counts volumes in one of $languages (or with no language): an
     * English edition is a different series from the owner's Japanese one of that title.
     *
     * @param  list<string>  $languages  language codes of the book being matched
     */
    private function fromLibrary(Isbn $isbn, ?string $title, User $user, array $languages = []): ?SeriesMatch
    {
        $listed = SeriesVolume::query()
            ->whereHas('series', fn ($query) => $query->ownedBy($user))
            ->with('series')
            ->orderBy('edition')
            ->get()
            ->first(fn (SeriesVolume $volume) => collect($volume->isbns ?? [])
                ->contains(fn (string $known) => Isbn::tryFrom($known)?->equals($isbn) ?? false));

        if ($listed !== null) {
            return $this->libraryMatch($listed->series, VolumeNumber::parse($listed->volume_number));
        }

        $key = $this->titleKey($title);

        if ($key === null) {
            return null;
        }

        $seriesIds = CollectionObject::query()->ownedBy($user)
            ->whereNotNull('serie_id')
            ->when($languages !== [], fn ($query) => $query->where(fn ($query) => $query
                ->whereDoesntHave('languages')
                ->orWhereHas('languages', fn ($query) => $query->whereIn('code', $languages))))
            ->get(['id', 'name', 'serie_id'])
            ->filter(fn (CollectionObject $other) => $this->titleKey($other->name) === $key)
            ->pluck('serie_id')
            ->unique();

        // Volumes of one title spread over several series: not ours to choose.
        if ($seriesIds->count() !== 1) {
            return null;
        }

        return $this->libraryMatch(Series::query()->findOrFail($seriesIds->first()), $this->titleParser->parse($title)?->volume);
    }

    /**
     * The series title of a book name: `Claymore, Vol. 15` and a bare `Claymore` (as
     * catalogues title many English manga volumes) both give the key of `Claymore`.
     */
    private function titleKey(?string $name): ?string
    {
        $key = Text::key($this->titleParser->parse($name)?->title ?? $name);

        return $key === '' ? null : $key;
    }

    private function libraryMatch(Series $series, ?VolumeNumber $volume): SeriesMatch
    {
        return new SeriesMatch(
            series: $series->reference() ?? new SeriesReference('manual', '', $series->name),
            volume: $volume,
            confidence: Confidence::High,
            source: self::SOURCE_LIBRARY,
        );
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
