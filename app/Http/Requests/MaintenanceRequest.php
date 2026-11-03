<?php

namespace App\Http\Requests;

use App\Models\Maintenance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MaintenanceRequest extends FormRequest
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
     * The result stays optional while the maintenance is still Scheduled
     * or On Progress; the after() hook below makes it mandatory for a
     * Done status and locks it while the ticket is still unresolved.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')],
            'maintenance_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:maintenance_date'],
            'status' => ['required', 'string', Rule::in(Maintenance::STATUSES)],
            'result' => ['nullable', 'string', Rule::in(Maintenance::RESULTS)],
            'evidence_photo' => ['nullable', 'file', 'image', 'max:4096'],
            'document' => ['nullable', 'file', 'max:10240'],
        ];
    }

    /**
     * Cross-field checks that rules() cannot express: a Done status must
     * carry an outcome, and the result may not change while the record's
     * trouble ticket is still unresolved (closing happens on the
     * Troubleshoot page, which then turns this result to Normal).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('status') === Maintenance::STATUS_DONE && ! $this->filled('result')) {
                    $validator->errors()->add('result', __('The result field is required when the maintenance is Done.'));
                }
            },
            function (Validator $validator) {
                $maintenance = $this->route('maintenance');

                if (! $maintenance instanceof Maintenance) {
                    return;
                }

                $ticket = $maintenance->troubleshoot;

                if ($ticket !== null
                    && $ticket->result === 'Masih Bermasalah'
                    && ($this->input('result') ?? null) !== $maintenance->result) {
                    $validator->errors()->add('result', __('Close the trouble ticket on the Troubleshoot page before changing this result.'));
                }
            },
        ];
    }
}
