<?php

namespace App\Actions\Admin;

use App\Models\Artist;
use App\Models\CollectionObject;
use App\Services\Catalogue\CatalogueLookupManager;
use App\Services\Romanization\NameRomanizer;
use App\Support\Catalogue\CatalogueContext;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Series\Text;
use Throwable;

/**
 * An admin's redo of the romanized names of a scanned item: its title, its publisher and
 * its author / artist. The barcode is looked up again (with the owner's keys) for the
 * kana readings; names without a reading fall back to their kana / Hangul or Wikidata.
 * Only placeholders are replaced: a romanization still equal to the original, or one filled
 * in automatically before. One typed by hand is kept.
 */
class RedoRomanization
{
    public function __construct(private readonly CatalogueLookupManager $catalogue, private readonly NameRomanizer $romanizer) {}

    /** @return int how many names changed */
    public function handle(CollectionObject $object): int
    {
        $object->loadMissing(['user', 'category', 'languages', 'publisher', 'bookDetail.artist', 'musicDetail.artist']);
        $language = $object->languages->first()?->code ?? 'ja';
        $result = $this->lookUp($object, $language);
        $changed = 0;

        if ($object->hasPlaceholderRomanization()
            && ($romanized = $this->romanizer->title($object->name, $result?->nameReading)) !== null
            && $romanized !== $object->name_romanized) {
            $object->update(['name_romanized' => $romanized, 'name_romanized_auto' => true]);
            $changed++;
        }

        $publisher = $object->publisher;

        if ($publisher?->hasPlaceholderRomanization()
            && ($romanized = $this->romanizer->publisher($publisher->name, $language)) !== null
            && $romanized !== $publisher->name_romanized) {
            $publisher->update(['name_romanized' => $romanized, 'name_romanized_auto' => true]);
            $changed++;
        }

        /** @var Artist|null $artist */
        $artist = $object->bookDetail?->artist ?? $object->musicDetail?->artist;
        // The reading belongs to the catalogue's creator: only use it for the same name.
        $reading = $artist !== null && $result !== null && Text::key($result->creator) === Text::key($artist->name) ? $result->creatorReading : null;

        if ($artist?->hasPlaceholderRomanization()
            && ($romanized = $this->romanizer->person($artist->name, $reading, $language)) !== null
            && $romanized !== $artist->name_romanized) {
            $artist->update(['name_romanized' => $romanized, 'name_romanized_auto' => true]);
            $changed++;
        }

        return $changed;
    }

    /** The catalogue's description of the item again, for its readings; null without one. */
    private function lookUp(CollectionObject $object, string $language): ?CatalogueResult
    {
        if ($object->barcode === null) {
            return null;
        }

        try {
            return $this->catalogue->lookup($object->barcode, new CatalogueContext($object->user, $object->category->topLevel(), $language))->result;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
