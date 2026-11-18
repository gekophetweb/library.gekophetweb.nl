<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The owner's own English name for a series, shown as its title. Blank clears it
 * (TrimStrings + ConvertEmptyStringsToNull turn whitespace into null).
 */
class UpdateSeriesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_english' => ['nullable', 'string', 'max:255'],
        ];
    }
}
