<?php

namespace App\Rules;

use App\Models\Category;
use App\Models\Genre;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A genre may only be attached to objects of its own top-level category (§3.4). */
class GenreFitsCategory implements ValidationRule
{
    public function __construct(private readonly Category $category) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $genre = Genre::query()->find($value);

        if ($genre === null || ! $genre->fitsCategory($this->category)) {
            $fail("That genre does not belong to {$this->category->topLevel()->name}.");
        }
    }
}
