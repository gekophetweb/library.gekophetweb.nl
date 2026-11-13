<?php

namespace App\Enums;

/**
 * Which category-specific detail table a category uses (guidelines §3.3). Stored on every
 * category; sub-categories copy it from their parent, so adding a sub-category (data)
 * needs no code — only a new top-level kind with its own detail table does.
 */
enum CategoryKind: string
{
    case Books = 'books';
    case Music = 'music';
    case Games = 'games';
}
