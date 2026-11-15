<?php

namespace App\Http\Requests;

use App\Support\Appearance\Appearance;
use App\Support\Appearance\AppearancePhotos;
use App\Support\Appearance\Background;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...Appearance::rules(),
            'photo' => ['nullable', 'file', 'mimetypes:'.implode(',', AppearancePhotos::MIME_TYPES), 'max:'.AppearancePhotos::MAX_KILOBYTES],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $wantsPhoto = $this->input('background') === Background::Photo->value;

            if ($wantsPhoto && ! $this->hasFile('photo') && $this->user()->appearance->photo === null) {
                $validator->errors()->add('photo', 'Choose an image to use as your background.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photo.mimetypes' => 'The image must be a JPG, PNG or WebP file.',
            'photo.max' => 'The image may be at most '.(AppearancePhotos::MAX_KILOBYTES / 1024).' MB.',
            'veil.between' => 'Pick a softening between :min and :max percent.',
        ];
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        return $this->safe()->only(Appearance::SETTABLE);
    }
}
