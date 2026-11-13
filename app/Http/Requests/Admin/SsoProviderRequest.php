<?php

namespace App\Http\Requests\Admin;

use App\Enums\SsoDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SsoProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $driver = SsoDriver::tryFrom((string) $this->input('driver'));
        $required = fn (string $setting) => $driver !== null && in_array($setting, $driver->requiredSettings(), true);

        return [
            'driver' => ['required', Rule::enum(SsoDriver::class)],
            'name' => ['required', 'string', 'max:100'],
            'client_id' => ['required', 'string', 'max:500'],
            // Write-only: required for a new provider, left empty on edit to keep the stored one.
            'client_secret' => [$this->route('provider') ? 'nullable' : 'required', 'string', 'max:2000'],
            'tenant' => [Rule::requiredIf($required('tenant')), 'nullable', 'string', 'max:255'],
            'issuer' => [Rule::requiredIf($required('issuer')), 'nullable', 'url:https', 'max:500'],
            'scopes' => ['nullable', 'string', 'max:500'],
            'enabled' => ['boolean'],
            'allow_registration' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /** @return array<string, mixed> */
    public function providerAttributes(): array
    {
        $attributes = [
            'driver' => $this->enum('driver', SsoDriver::class),
            'name' => $this->string('name')->trim()->value(),
            'client_id' => $this->string('client_id')->trim()->value(),
            'settings' => array_filter([
                'tenant' => $this->input('tenant'),
                'issuer' => $this->input('issuer'),
                'scopes' => $this->input('scopes'),
            ], fn ($value) => $value !== null && $value !== ''),
            'enabled' => $this->boolean('enabled'),
            'allow_registration' => $this->boolean('allow_registration'),
            'sort' => (int) $this->input('sort', 0),
        ];

        if ($this->filled('client_secret')) {
            $attributes['client_secret'] = $this->input('client_secret');
        }

        return $attributes;
    }
}
