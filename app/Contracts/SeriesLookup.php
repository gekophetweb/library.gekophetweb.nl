<?php

namespace App\Contracts;

use App\Support\Isbn;
use App\Support\Series\SeriesMatch;
use App\Support\Series\SeriesReference;
use App\Support\Series\SeriesVolumeData;
use Illuminate\Support\Collection;

/**
 * Answers "which series is this book in, and what else is in it?".
 *
 * Separate from CatalogueLookup ("what is this item?"): the question and the providers
 * differ. Implementations are chained per scan language by SeriesLookupManager, so a
 * provider must return null / an empty collection on a miss or a failed call rather
 * than throw.
 */
interface SeriesLookup
{
    /** Short provider key used in logs and shown to the user, e.g. `ndl`. */
    public function name(): string;

    /**
     * ISBN → series reference + this book's volume number, or null.
     *
     * @param  string  $language  ISO 639-1 code of the scan language, e.g. `ja`, `en`.
     */
    public function resolve(Isbn $isbn, string $language): ?SeriesMatch;

    /** Whether volumes() can list the given series (i.e. it comes from this source). */
    public function supports(SeriesReference $series): bool;

    /**
     * Series reference → every known volume, ordered by volume number.
     *
     * @return Collection<int, SeriesVolumeData>
     */
    public function volumes(SeriesReference $series): Collection;
}
