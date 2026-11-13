<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A user's message for the suggestion box. Status and reply are the admin's to set. */
class StoreSuggestionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
