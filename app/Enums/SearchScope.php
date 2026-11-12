<?php

namespace App\Enums;

/** What the search box looks in: titles, creator names, or both. */
enum SearchScope: string
{
    case All = 'all';
    case Title = 'title';
    case Creator = 'creator';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Everything',
            self::Title => 'Title',
            self::Creator => 'Author / artist',
        };
    }

    public function includesTitles(): bool
    {
        return $this !== self::Creator;
    }

    public function includesCreators(): bool
    {
        return $this !== self::Title;
    }
}
