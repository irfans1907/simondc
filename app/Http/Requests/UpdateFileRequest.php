<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the file being renamed from the route.
     */
    private function routeFile(): File
    {
        $file = $this->route('file');

        if (! $file instanceof File) {
            throw new RuntimeException('File not bound to the request.');
        }

        return $file;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('files')->where(fn ($query) => $query->where('parent_id', $this->routeFile()->parent_id))->ignore($this->routeFile()->id),
            ],
        ];
    }
}
