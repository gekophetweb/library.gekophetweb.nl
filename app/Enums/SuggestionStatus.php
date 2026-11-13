<?php

namespace App\Enums;

/**
 * Where an admin has put a suggestion. A fixed workflow, not catalogue data, so an enum.
 */
enum SuggestionStatus: string
{
    case New = 'new';
    case Planned = 'planned';
    case Done = 'done';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Planned => 'Planned',
            self::Done => 'Done',
            self::Declined => 'Declined',
        };
    }

    /** Tailwind classes for the status badge. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200',
            self::Planned => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
            self::Done => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
            self::Declined => 'bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300',
        };
    }
}
