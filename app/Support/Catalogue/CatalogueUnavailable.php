<?php

namespace App\Support\Catalogue;

use RuntimeException;

/**
 * A catalogue gave no usable answer to a lookup: timed out, refused (e.g. Google Books'
 * keyless quota, 429) or failed. Unlike "not found", trying again later can help.
 */
final class CatalogueUnavailable extends RuntimeException
{
    public static function from(string $provider, ?int $status = null): self
    {
        return new self($status ? "{$provider} answered {$status}" : "{$provider} did not answer");
    }
}
