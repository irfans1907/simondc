<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomerRequest extends FormRequest
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
        $panelCategoryIds = Category::idsByName('Panel Power');

        return [
            'rack_id' => [
                'required',
                'integer',
                Rule::unique('customers', 'rack_id')->ignore($this->route('customer')),
            ],
            'city' => ['required', 'string', Rule::in(Customer::CITIES)],
            'location' => ['required', 'string', Rule::in(Customer::LOCATIONS)],
            'customer_name' => ['required', 'string', 'max:255'],
            'service_type' => ['nullable', 'string', 'max:255'],
            'sales_channel' => ['nullable', 'string', 'max:255'],
            'business_status' => ['nullable', 'string', Rule::in(Customer::BUSINESS_STATUSES)],
            'service_status' => ['nullable', 'string', Rule::in(Customer::SERVICE_STATUSES)],
            'order_number' => ['nullable', 'string', 'max:255'],
            'sid_rack' => ['nullable', 'string', 'max:255'],
            'activation_date' => ['nullable', 'date'],
            'power_source' => ['nullable', 'string', Rule::in(Customer::POWER_SOURCES)],
            'panel_asset_id' => [
                'nullable',
                'integer',
                Rule::exists('assets', 'id')
                    ->where(fn ($query) => $query->whereIn('category_id', $panelCategoryIds)),
            ],
            'panel_mcb_id' => [
                'nullable',
                'integer',
                Rule::exists('panel_mcbs', 'id')->where('asset_id', $this->input('panel_asset_id')),
            ],
            'panel_asset_id_2' => [
                'nullable',
                'integer',
                Rule::exists('assets', 'id')
                    ->where(fn ($query) => $query->whereIn('category_id', $panelCategoryIds)),
            ],
            'panel_mcb_id_2' => [
                'nullable',
                'integer',
                Rule::exists('panel_mcbs', 'id')->where('asset_id', $this->input('panel_asset_id_2')),
            ],
            'connectivity' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'switch_type' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'string', 'max:255'],
            'vlan' => ['nullable', 'string', 'max:255'],
            'service_vlan' => ['nullable', 'string', 'max:255'],
            'account_manager' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Cross-field checks that rules() cannot express: the panel/MCB pair
     * count follows the power source, both MCBS must be distinct, and one
     * MCB slot can feed at most one customer.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->validatePairCount($validator);
                $this->validateDistinctPairs($validator);
                $this->validateMcbAvailability($validator);
            },
        ];
    }

    /**
     * Dual Source requires the second panel/MCB pair; Single Source forbids
     * it. Both stay optional while power_source is empty.
     */
    private function validatePairCount(Validator $validator): void
    {
        $powerSource = $this->input('power_source');

        if ($powerSource === 'Dual Source') {
            if (! $this->filled('panel_asset_id_2')) {
                $validator->errors()->add('panel_asset_id_2', __('A second panel is required for Dual Source.'));
            }

            if (! $this->filled('panel_mcb_id_2')) {
                $validator->errors()->add('panel_mcb_id_2', __('A second MCB is required for Dual Source.'));
            }
        } elseif ($powerSource === 'Single Source') {
            if ($this->filled('panel_asset_id_2')) {
                $validator->errors()->add('panel_asset_id_2', __('A second panel is only allowed for Dual Source.'));
            }

            if ($this->filled('panel_mcb_id_2')) {
                $validator->errors()->add('panel_mcb_id_2', __('A second MCB is only allowed for Dual Source.'));
            }
        }
    }

    /**
     * A customer cannot feed both sources from the same MCB slot.
     */
    private function validateDistinctPairs(Validator $validator): void
    {
        if ($this->filled('panel_mcb_id')
            && $this->filled('panel_mcb_id_2')
            && (string) $this->input('panel_mcb_id') === (string) $this->input('panel_mcb_id_2')) {
            $validator->errors()->add('panel_mcb_id_2', __('The second MCB must differ from the first.'));
        }
    }

    /**
     * One MCB slot can feed at most one customer, across both pair columns.
     */
    private function validateMcbAvailability(Validator $validator): void
    {
        $customer = $this->route('customer');

        foreach (['panel_mcb_id', 'panel_mcb_id_2'] as $field) {
            $mcbId = $this->input($field);

            if (! $this->filled($field)) {
                continue;
            }

            $taken = Customer::query()
                ->where(function (Builder $query) use ($mcbId) {
                    $query->where('panel_mcb_id', $mcbId)
                        ->orWhere('panel_mcb_id_2', $mcbId);
                })
                ->when($customer, fn (Builder $query) => $query->whereKeyNot($customer->id))
                ->exists();

            if ($taken) {
                $validator->errors()->add($field, __('This MCB is already assigned to another customer.'));
            }
        }
    }
}
