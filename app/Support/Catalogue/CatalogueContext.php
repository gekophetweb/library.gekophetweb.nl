<?php

namespace App\Support\Catalogue;

use App\Models\Category;
use App\Models\User;

/** What the scanner modal chose: whose lookup, which category and which language (§4). */
final readonly class CatalogueContext
{
    public function __construct(
        public User $user,
        public Category $category,
        public string $language, // ISO 639-1, e.g. "ja"
    ) {}
}
