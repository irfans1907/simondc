<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\VlanTagRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VlanTagRequestRequest extends FormRequest
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
     * Every switch row must point at a Network asset (the picker only
     * offers that category); the one-switch-per-non-LAG rule lives in
     * after() because it depends on the submitted row count.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')],
            'order_number' => ['required', 'string', 'max:255'],
            'sid' => ['required', 'string', 'max:255'],
            'service_name' => ['required', 'string', 'max:255'],
            'vlan' => ['required', 'string', 'max:255'],
            'mode' => ['required', 'string', Rule::in(VlanTagRequest::MODES)],
            'switches' => ['required', 'array', 'min:1'],
            'switches.*.asset_id' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')->whereIn('category_id', $this->categoryIds()),
            ],
            'switches.*.port_number' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Cross-field check that rules() cannot express: only a LAG request
     * may span more than one switch.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $switches = $this->input('switches');

                if (! is_array($switches) || $this->input('mode') === 'LAG') {
                    return;
                }

                if (count($switches) > 1) {
                    $validator->errors()->add('switches', __('Only a LAG request may list more than one switch.'));
                }
            },
        ];
    }

    /**
     * Every category id whose name is Network (plus descendants).
     *
     * @return list<int>
     */
    private function categoryIds(): array
    {
        return Category::idsByName('Network');
    }
}
