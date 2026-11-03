<?php

namespace App\Http\Requests;

use App\Models\Rack;
use App\Models\RackType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RackRequest extends FormRequest
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
            'rack_code' => ['required', 'string', 'max:50', Rule::unique('racks', 'rack_code')->ignore($this->route('rack'))],
            'floor' => ['required', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(RackType::query()->pluck('name'))],
            'capacity' => ['required', 'string', 'max:255'],
            'dimension' => ['required', 'string', 'max:255'],
            'pdu_outlet_1' => ['required', Rule::in(Rack::PDU_STATES)],
            'pdu_outlet_2' => ['required', Rule::in(Rack::PDU_STATES)],
            'manufacture_year' => ['required', 'string', 'max:255'],
            'operation_year' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
