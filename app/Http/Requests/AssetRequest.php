<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssetRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_path' => ['required', 'array', 'min:1'],
            'category_path.0' => ['required', 'string', 'max:100'],
            'category_path.*' => ['string', 'max:100'],
            'brand' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'serial_number' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'string', 'max:255'],
            'manufacture_year' => ['required', 'string', 'max:255'],
            'operation_year' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'string', 'max:255'],
            'floor' => ['required', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
