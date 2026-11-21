<?php

namespace App\Http\Requests;

use App\Support\Series\VolumeNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Picking a book's series by hand: one of the owner's series, or a new name. Both empty
 * takes the book out of its series.
 */
class UpdateObjectSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless($this->route('object')->user_id === $this->user()->id, 404);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'serie_id' => ['nullable', Rule::exists('series', 'id')->where('user_id', $this->user()->id)],
            'series_name' => ['nullable', 'string', 'max:255'],
            'series_volume' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if (VolumeNumber::parse((string) $value) === null) {
                    $fail('The volume must be a number, such as 3 or 3.5.');
                }
            }],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['serie_id.exists' => 'Choose one of your own series.'];
    }
}
