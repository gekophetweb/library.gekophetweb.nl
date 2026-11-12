<?php

namespace App\Http\Requests;

use App\Enums\CategoryKind;
use App\Enums\SearchScope;
use App\Models\Category;
use App\Models\CollectionObject;
use App\Models\Language;
use App\Models\User;
use App\Support\Catalogue\CatalogueContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The search box: a term and a scope (All / Title / Creator / Not owned yet). The
 * catalogue scope also takes a category and a language, like the scanner dialog.
 */
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
            'category_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'sub_category_id' => ['nullable', 'integer'],
            'language_id' => ['nullable', Rule::exists('languages', 'id')],
        ];
    }

    /** The sub-category to file added books under; ignored when it is not part of the category. */
    public function subCategory(): ?Category
    {
        return $this->filled('sub_category_id')
            ? $this->category()->children()->whereKey($this->integer('sub_category_id'))->first()
            : null;
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

    /** Defaults to Books. */
    public function category(): Category
    {
        return ($this->filled('category_id') ? Category::query()->find($this->integer('category_id')) : null)
            ?? Category::query()->whereNull('parent_id')->where('kind', CategoryKind::Books)->orderBy('sort')->orderBy('id')->firstOrFail();
    }

    /** Defaults to the language of the user's last scan, else Japanese, else the first one. */
    public function language(): Language
    {
        $id = $this->filled('language_id') ? $this->integer('language_id') : ($this->hasSession() ? $this->session()->get('scan.setup.language_id') : null);

        return ($id ? Language::query()->find($id) : null)
            ?? Language::query()->where('code', 'ja')->first()
            ?? Language::query()->orderBy('name')->firstOrFail();
    }

    public function catalogueContext(): CatalogueContext
    {
        /** @var User $user */
        $user = $this->user();

        return new CatalogueContext($user, $this->category(), $this->language()->code);
    }
}
