<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * For models with name / name_romanized and the `name_romanized_auto` flag: whether the
 * romanized name may be replaced by an automatic one.
 */
trait HasRomanizedName
{
    public function initializeHasRomanizedName(): void
    {
        $this->mergeCasts(['name_romanized_auto' => 'boolean']);
    }

    /**
     * True for a missing romanization, a copy of the original name (the fallback when none
     * was known) or one filled in automatically before; false for one typed by hand.
     */
    public function hasPlaceholderRomanization(): bool
    {
        return $this->name_romanized === null
            || $this->name_romanized === ''
            || $this->name_romanized === $this->name
            || $this->name_romanized_auto;
    }

    /**
     * The query form of hasPlaceholderRomanization().
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function wherePlaceholderRomanization(Builder $query): void
    {
        $column = $this->qualifyColumn('name_romanized');

        $query->where(fn (Builder $query) => $query
            ->whereNull($column)
            ->orWhere($column, '')
            ->orWhereColumn($column, $this->qualifyColumn('name'))
            ->orWhere($this->qualifyColumn('name_romanized_auto'), true));
    }
}
