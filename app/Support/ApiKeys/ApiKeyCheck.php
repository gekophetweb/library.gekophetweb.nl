<?php

namespace App\Support\ApiKeys;

/**
 * What a provider said about a key tried before saving: accepted, refused (with the
 * provider's reason), or no usable answer (provider down, quota used up) so it is unknown.
 */
final readonly class ApiKeyCheck
{
    private function __construct(public bool $accepted, public bool $refused, public ?string $message = null) {}

    public static function accepted(): self
    {
        return new self(true, false);
    }

    public static function refused(string $reason): self
    {
        return new self(false, true, $reason);
    }

    public static function unknown(string $reason): self
    {
        return new self(false, false, $reason);
    }
}
