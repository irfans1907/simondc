<?php

namespace App\Http\Requests;

use App\Helpers\AssetSheetHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sheet' => ['nullable', 'string'],
            'name' => [
                Rule::requiredIf(fn (): bool => ! $this->submitsBatteryCategory()
                    && ! $this->submitsCameraCategory()),
                'string',
                'max:255',
            ],
            'parent_asset_id' => [
                Rule::requiredIf(fn (): bool => $this->submitsBatteryCategory()),
                'nullable',
                'integer',
                'exists:assets,id',
            ],
            'category_path' => ['required', 'array', 'min:1'],
            'category_path.0' => ['required', 'string', 'max:100'],
            'category_path.*' => ['string', 'max:100'],
            'alias' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:255'],
            'owner' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'manufacture_year' => ['nullable', 'string', 'max:255'],
            'operation_year' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'room' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'details' => ['nullable', 'array'],
            'details.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Whether the submitted category path belongs to a battery category.
     */
    private function submitsBatteryCategory(): bool
    {
        $sheet = AssetSheetHelper::sheetKey(
            is_string($this->input('sheet')) ? $this->input('sheet') : null,
        );

        $path = $this->input('category_path');

        return is_array($path) && AssetSheetHelper::pathIsBattery($sheet, $path);
    }

    /**
     * Whether the submitted category path selects the sheet's camera
     * category. Camera names are derived from the alias/channel, so they are
     * not required from the form.
     */
    private function submitsCameraCategory(): bool
    {
        $sheet = AssetSheetHelper::sheetKey(
            is_string($this->input('sheet')) ? $this->input('sheet') : null,
        );

        $path = $this->input('category_path');

        return is_array($path) && AssetSheetHelper::pathIsCamera($sheet, $path);
    }
}
