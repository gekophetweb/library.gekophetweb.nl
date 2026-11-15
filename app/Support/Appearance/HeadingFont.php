<?php

namespace App\Support\Appearance;

enum HeadingFont: string
{
    case Bricolage = 'bricolage';
    case Fraunces = 'fraunces';
    case Instrument = 'instrument';

    public function label(): string
    {
        return match ($this) {
            self::Bricolage => 'Bricolage',
            self::Fraunces => 'Fraunces',
            self::Instrument => 'Instrument',
        };
    }

    /** The CSS font-family stack, also used for the samples on the settings page. */
    public function stack(): string
    {
        return match ($this) {
            self::Bricolage => "'Bricolage Grotesque', ui-sans-serif, system-ui, sans-serif",
            self::Fraunces => "'Fraunces', ui-serif, Georgia, serif",
            self::Instrument => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
        };
    }
}
