<?php

namespace App\Support\Scan;

use App\Models\Category;
use App\Models\Language;

/** What the scanner dialog asked for (guidelines §4): category, optional sub-category, language. */
final readonly class ScanSetup
{
    public function __construct(
        public Category $category,
        public ?Category $subCategory,
        public Language $language,
    ) {}
}
