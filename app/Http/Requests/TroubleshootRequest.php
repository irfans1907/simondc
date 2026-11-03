<?php

namespace App\Http\Requests;

use App\Models\Troubleshoot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TroubleshootRequest extends FormRequest
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
     * Work dates are optional while the ticket is Unresolved; closing it
     * (result Normal) demands both — see the after() hook. A close date
     * can never precede the start date.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')],
            'troubleshoot_date' => ['required', 'date'],
            'started_date' => ['nullable', 'date'],
            'closed_date' => ['nullable', 'date', 'after_or_equal:started_date'],
            'result' => ['required', 'string', Rule::in(Troubleshoot::RESULTS)],
            'evidence_photo' => ['nullable', 'file', 'image', 'max:4096'],
            'document' => ['nullable', 'file', 'max:10240'],
        ];
    }

    /**
     * Cross-field check that rules() cannot express: closing a ticket
     * requires knowing when the work started and when it finished.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('result') !== 'Normal') {
                    return;
                }

                if (! $this->filled('started_date')) {
                    $validator->errors()->add('started_date', __('The started date field is required when the ticket is Solved.'));
                }

                if (! $this->filled('closed_date')) {
                    $validator->errors()->add('closed_date', __('The closed date field is required when the ticket is Solved.'));
                }
            },
        ];
    }
}
