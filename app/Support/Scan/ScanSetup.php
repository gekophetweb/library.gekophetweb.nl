<?php

namespace App\Support\Scan;

use App\Enums\OwnershipStatus;
use App\Models\Category;
use App\Models\Language;

/**
 * What the scanner dialog asked for (guidelines §4): category, optional sub-category,
 * language, and whether the scanned items are owned or wanted.
 */
final readonly class ScanSetup
{
    public function __construct(
        public Category $category,
        public ?Category $subCategory,
        public Language $language,
        public OwnershipStatus $ownership = OwnershipStatus::Owned,
    ) {}
}
