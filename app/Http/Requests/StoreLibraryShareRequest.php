<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Series;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A new share link. The scope is one field — `library`, `category:7` or `series:3` — so
 * the "Share this category / series" buttons can post a single hidden input. A series
 * must be the user's own; a category must be a top-level one.
 */
class StoreLibraryShareRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        [$type, $id] = array_pad(explode(':', (string) $this->input('scope', 'library'), 2), 2, null);

        $this->merge(['scope_type' => $type, 'scope_id' => $id]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope_type' => ['required', 'in:library,category,series'],
            'scope_id' => [
                Rule::requiredIf(fn () => $this->input('scope_type') !== 'library'),
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    $exists = match ($this->input('scope_type')) {
                        'category' => Category::query()->whereNull('parent_id')->whereKey($value)->exists(),
                        'series' => Series::query()->ownedBy($this->user())->whereKey($value)->exists(),
                        default => true,
                    };

                    if (! $exists) {
                        $fail('Choose one of your own categories or series.');
                    }
                },
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'recipient_email' => ['nullable', 'email', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if (Str::lower((string) $value) === Str::lower($this->user()->email)) {
                    $fail('That is your own address.');
                }
            }],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['scope_id' => 'scope', 'scope_type' => 'scope'];
    }

    public function shareable(): Category|Series|null
    {
        return match ($this->validated('scope_type')) {
            'category' => Category::query()->findOrFail($this->validated('scope_id')),
            'series' => Series::query()->ownedBy($this->user())->findOrFail($this->validated('scope_id')),
            default => null,
        };
    }
}
