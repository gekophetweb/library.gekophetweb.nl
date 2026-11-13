<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A comment in a suggestion's thread. */
class StoreSuggestionCommentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }
}
