<?php

namespace App\Http\Requests;

use App\Enums\OwnershipStatus;
use App\Models\Category;
use App\Models\Language;
use App\Support\Catalogue\CatalogueResult;
use App\Support\Isbn;
use App\Support\Scan\ScanSetup;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Add to wishlist" on a catalogue search result: the result's fields plus where it goes
 * (category, optional sub-category, language), as with a scan. The ISBN is required — it
 * is what a later scan matches to promote the item to owned.
 */
class StoreWishlistItemRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'sub_category_id' => ['nullable', Rule::exists('categories', 'id')->where('parent_id', $this->integer('category_id'))],
            'language_id' => ['required', Rule::exists('languages', 'id')],
            'barcode' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if (Isbn::tryFrom((string) $value) === null) {
                    $fail('That is not a valid ISBN.');
                }
            }],
            'name' => ['required', 'string', 'max:255'],
            'name_romanized' => ['nullable', 'string', 'max:255'],
            'name_english' => ['nullable', 'string', 'max:255'],
            'creator' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'release_date' => ['nullable', 'string', 'max:10', 'regex:/^\d{4}(-\d{2}){0,2}$/'],
            'cover_url' => ['nullable', 'url:https', 'max:1000'],
            'source' => ['nullable', 'string', 'max:40'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['sub_category_id.exists' => 'That sub-category is not part of this category.'];
    }

    public function setup(): ScanSetup
    {
        return new ScanSetup(
            Category::query()->findOrFail($this->integer('category_id')),
            $this->filled('sub_category_id') ? Category::query()->findOrFail($this->integer('sub_category_id')) : null,
            Language::query()->findOrFail($this->integer('language_id')),
            OwnershipStatus::Wishlist,
        );
    }

    public function result(): CatalogueResult
    {
        $data = $this->validated();

        return new CatalogueResult(
            source: $data['source'] ?? 'search',
            barcode: Isbn::fromString($data['barcode'])->value,
            name: $data['name'],
            nameRomanized: $data['name_romanized'] ?? null,
            nameEnglish: $data['name_english'] ?? null,
            creator: $data['creator'] ?? null,
            publisher: $data['publisher'] ?? null,
            releaseDate: $data['release_date'] ?? null,
            coverUrl: $data['cover_url'] ?? null,
        );
    }
}
