<?php

namespace App\Http\Requests;

use App\Enums\SearchScope;
use App\Models\CollectionObject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The search box: a term and a scope (All / Title / Creator). */
class SearchRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', Rule::enum(SearchScope::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** The trimmed, whitespace-collapsed term; null when blank. */
    public function term(): ?string
    {
        return CollectionObject::normalizeSearchTerm($this->validated('q'));
    }

    public function scope(): SearchScope
    {
        return $this->enum('scope', SearchScope::class) ?? SearchScope::All;
    }
}
