<?php

namespace App\Enums;

/**
 * Whether an item is in the collection or only wanted. A closed two-value domain concept
 * that branches code paths (scan dedup, series completion, counts), so an enum rather than
 * a taxonomy table (see own-wishlist plan §1).
 */
enum OwnershipStatus: string
{
    case Owned = 'owned';
    case Wishlist = 'wishlist';

    public function label(): string
    {
        return match ($this) {
            self::Owned => 'Owned',
            self::Wishlist => 'Wishlist',
        };
    }
}
