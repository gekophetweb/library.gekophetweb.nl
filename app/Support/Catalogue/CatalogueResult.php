<?php

namespace App\Support\Catalogue;

/** One provider's description of an item, normalized. Serializable so batch scans can stage it. */
final readonly class CatalogueResult
{
    public function __construct(
        public string $source,
        public string $barcode,
        public string $name,
        public ?string $nameRomanized = null,
        public ?string $nameEnglish = null,
        public ?string $creator = null,
        public ?string $publisher = null,
        public ?string $releaseDate = null, // "1990", "1990-12" or "1990-12-01"
        public ?string $coverUrl = null,
        public ?string $languageCode = null,
    ) {}

    /** Fills this result's gaps from a later provider's result for the same item. */
    public function mergedWith(self $other): self
    {
        $fields = get_object_vars($this);

        foreach (get_object_vars($other) as $field => $value) {
            $fields[$field] ??= $value;
        }

        return new self(...$fields);
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param  array<string, string|null>  $data */
    public static function fromArray(array $data): self
    {
        $fields = ['source', 'barcode', 'name', 'nameRomanized', 'nameEnglish', 'creator', 'publisher', 'releaseDate', 'coverUrl', 'languageCode'];

        return new self(...array_intersect_key($data, array_flip($fields)));
    }
}
