<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** An admin promoting another user to admin, or taking admin away. */
class UpdateUserRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_admin' => ['required', 'boolean'],
        ];
    }
}
