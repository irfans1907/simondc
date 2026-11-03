<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File as FileRule;

class StoreFileRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array'],
            'files.*' => ['file', FileRule::default()->max(102400)],
            'parent_id' => ['nullable', 'integer', 'exists:files,id'],
            'relative_paths' => ['nullable', 'array'],
            'relative_paths.*' => ['nullable', 'string'],
        ];
    }
}
