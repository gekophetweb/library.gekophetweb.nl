<?php

namespace App\Support\Appearance;

enum ThemeMode: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::System => 'Same as device',
            self::Light => 'Light',
            self::Dark => 'Dark',
        };
    }
}
