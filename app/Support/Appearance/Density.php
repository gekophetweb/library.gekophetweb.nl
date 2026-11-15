<?php

namespace App\Support\Appearance;

enum Density: string
{
    case Comfortable = 'comfortable';
    case Compact = 'compact';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
