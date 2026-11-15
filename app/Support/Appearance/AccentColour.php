<?php

namespace App\Support\Appearance;

/**
 * The accent presets. No free hex: each preset has a light and a dark shade that were
 * checked for 4.5:1 contrast against both themes' cards and with their button text.
 * The CSS for each lives in resources/css/app.css (`[data-accent=…]`).
 */
enum AccentColour: string
{
    case Stamp = 'stamp';
    case Teal = 'teal';
    case Indigo = 'indigo';
    case Plum = 'plum';
    case Mustard = 'mustard';
    case Forest = 'forest';

    public function label(): string
    {
        return match ($this) {
            self::Stamp => 'Stamp red',
            default => ucfirst($this->value),
        };
    }

    public function light(): string
    {
        return match ($this) {
            self::Stamp => '#c4412b',
            self::Teal => '#0f6e78',
            self::Indigo => '#3f4fb8',
            self::Plum => '#8a3a7a',
            self::Mustard => '#7d5c00',
            self::Forest => '#2f6b2f',
        };
    }

    public function dark(): string
    {
        return match ($this) {
            self::Stamp => '#ff7a5c',
            self::Teal => '#4fd1d9',
            self::Indigo => '#9aa6ff',
            self::Plum => '#e590d4',
            self::Mustard => '#f2c94c',
            self::Forest => '#8ed38e',
        };
    }
}
