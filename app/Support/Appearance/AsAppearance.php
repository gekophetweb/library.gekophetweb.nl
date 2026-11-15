<?php

namespace App\Support\Appearance;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * `users.appearance` as an Appearance; null in the database reads as the standard look,
 * and the standard look is saved as null.
 *
 * @implements CastsAttributes<Appearance, Appearance|array<string, mixed>|null>
 */
class AsAppearance implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Appearance
    {
        return Appearance::fromArray(is_string($value) ? json_decode($value, true) : null);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $appearance = match (true) {
            $value === null => null,
            $value instanceof Appearance => $value,
            is_array($value) => Appearance::fromArray($value),
            default => throw new InvalidArgumentException('Appearance must be an Appearance or an array.'),
        };

        return $appearance === null || $appearance->isDefault() ? null : json_encode($appearance->toArray());
    }
}
