<?php

namespace App\Http\Requests\Admin;

use App\Enums\SuggestionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** An admin triaging a suggestion: its status and an optional reply the sender sees. */
class UpdateSuggestionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SuggestionStatus::class)],
            'admin_reply' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
