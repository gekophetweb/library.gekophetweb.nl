<?php

namespace App\Enums;

/**
 * What the search box looks in: titles, creator names or both in the user's own library,
 * or the external catalogues for books not owned yet (an explicit choice: it costs
 * external calls).
 */
enum SearchScope: string
{
    case All = 'all';
    case Title = 'title';
    case Creator = 'creator';
    case Catalogue = 'catalogue';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Everything',
            self::Title => 'Title',
            self::Creator => 'Author / artist',
            self::Catalogue => 'Not owned yet',
        };
    }

    public function includesTitles(): bool
    {
        return in_array($this, [self::All, self::Title], true);
    }

    public function includesCreators(): bool
    {
        return in_array($this, [self::All, self::Creator], true);
    }

    public function isCatalogue(): bool
    {
        return $this === self::Catalogue;
    }
}
