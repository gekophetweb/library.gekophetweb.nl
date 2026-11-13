<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Name search for every model with the name / name_romanized / name_english triple
 * (guidelines §3.4). A plain `LIKE '%term%'`: MariaDB has no ngram fulltext parser for
 * Japanese, the `_ci` collation already ignores case, and one person's catalogue is small.
 */
trait SearchesNames
{
    /** The escape character for LIKE patterns: '!' works the same on MariaDB and SQLite. */
    public const string LIKE_ESCAPE = '!';

    /** Neutralises the LIKE wildcards in a user's term, so "%" matches a literal "%". */
    public static function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /** Trims and collapses whitespace runs; null for a blank term. */
    public static function normalizeSearchTerm(?string $term): ?string
    {
        $term = trim((string) preg_replace('/[\s\x{3000}]+/u', ' ', (string) $term));

        return $term === '' ? null : $term;
    }

    /**
     * Matches any of the three name columns. A term without spaces also matches a stored
     * name with spaces removed, so "三浦建太郎" finds "三浦 建太郎".
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function whereNameLike(Builder $query, string $term): void
    {
        $pattern = '%'.static::escapeLike($term).'%';
        $escape = " escape '".self::LIKE_ESCAPE."'";
        $columns = array_map(fn (string $column) => $query->getModel()->qualifyColumn($column), ['name', 'name_romanized', 'name_english']);
        $grammar = $query->getQuery()->getGrammar();

        $query->where(function (Builder $query) use ($columns, $pattern, $escape, $term, $grammar) {
            foreach ($columns as $column) {
                $wrapped = $grammar->wrap($column);
                $query->orWhereRaw("{$wrapped} like ?{$escape}", [$pattern]);

                if (! str_contains($term, ' ')) {
                    $query->orWhereRaw("replace(replace({$wrapped}, ' ', ''), '　', '') like ?{$escape}", [$pattern]);
                }
            }
        });
    }
}
