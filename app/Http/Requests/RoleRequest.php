<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $ignore = $role instanceof Role ? $role->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('spatie_roles')->ignore($ignore),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:spatie_permissions,name'],
        ];
    }
}
