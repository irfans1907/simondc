<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class UserUpdateRequest extends FormRequest
{
    /**
     * Get the user being updated from the route.
     */
    private function routeUser(): User
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            throw new RuntimeException('User not bound to the request.');
        }

        return $user;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->routeUser()->id),
            ],
            'role' => ['sometimes', 'string', Rule::exists('spatie_roles', 'name')],
        ];
    }
}
