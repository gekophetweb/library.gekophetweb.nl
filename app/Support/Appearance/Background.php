<?php

namespace App\Support\Appearance;

enum Background: string
{
    case Ruled = 'ruled';
    case Plain = 'plain';
    case Dots = 'dots';
    case Grid = 'grid';
    case Photo = 'photo';

    public function label(): string
    {
        return match ($this) {
            self::Ruled => 'Ruled card',
            self::Plain => 'Plain',
            self::Dots => 'Dots',
            self::Grid => 'Graph paper',
            self::Photo => 'Your photo',
        };
    }
}
