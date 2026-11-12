<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Language;
use App\Support\Scan\ScanSetup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The scanner dialog: a category (and sub-category if it has any), a language, a mode (§4). */
class StartScanRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = Category::query()->whereNull('parent_id')->find($this->integer('category_id'));

        return [
            'category_id' => ['required', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'sub_category_id' => [
                Rule::requiredIf(fn () => $category?->children()->exists() ?? false),
                'nullable',
                Rule::exists('categories', 'id')->where('parent_id', $category?->id ?? 0),
            ],
            'language_id' => ['required', Rule::exists('languages', 'id')],
            'mode' => ['required', 'in:single,batch'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Choose one of the main categories.',
            'sub_category_id.required' => 'Choose a sub-category.',
        ];
    }

    public function setup(): ScanSetup
    {
        return new ScanSetup(
            Category::query()->findOrFail($this->integer('category_id')),
            $this->filled('sub_category_id') ? Category::query()->findOrFail($this->integer('sub_category_id')) : null,
            Language::query()->findOrFail($this->integer('language_id')),
        );
    }
}
