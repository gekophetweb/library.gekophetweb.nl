<?php

namespace App\Http\Requests;

use App\Models\CollectionObject;
use App\Rules\GenreFitsCategory;
use App\Support\Series\VolumeNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Editing an item. The required fields are the ones from guidelines §7. */
class UpdateCollectionObjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Someone else's item does not exist as far as this user is concerned: 404, and
        // before validation, so the errors cannot be used to probe it.
        abort_unless($this->route('object')->user_id === $this->user()->id, 404);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var CollectionObject $object */
        $object = $this->route('object');

        return [
            'name' => ['required', 'string', 'max:255'],
            'name_romanized' => ['required', 'string', 'max:255'],
            'name_english' => ['nullable', 'string', 'max:255'],
            'sub_category_id' => ['nullable', Rule::exists('categories', 'id')->where('parent_id', $object->category_id)],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['integer', 'distinct', Rule::exists('languages', 'id')],
            'genres' => ['nullable', 'array'],
            'genres.*' => ['integer', 'distinct', new GenreFitsCategory($object->category)],
            'publisher_id' => ['nullable', Rule::exists('publishers', 'id')],
            'release_date' => ['nullable', 'date'],
            'serie_id' => ['nullable', Rule::exists('series', 'id')->where('user_id', $this->user()->id)],
            'volume' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if (VolumeNumber::parse((string) $value) === null) {
                    $fail('The volume must be a number, such as 3 or 3.5.');
                }
            }],
            'artist_id' => ['nullable', Rule::exists('artists', 'id')],
            'platform_id' => ['nullable', Rule::exists('platforms', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'languages.required' => 'Choose at least one language.',
            'sub_category_id.exists' => 'That sub-category is not part of this category.',
            'serie_id.exists' => 'Choose one of your own series.',
        ];
    }
}
