<?php

namespace App\Http\Requests;

use App\Helpers\AssetSheetHelper;

class AssetBulkRequest extends AssetRequest
{
    /**
     * Shared device rules plus one validation block per camera row.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $sheet = AssetSheetHelper::sheetKey(
            is_string($this->input('sheet')) ? $this->input('sheet') : null,
        );

        $multi = AssetSheetHelper::multiRow($sheet);
        $keys = $multi['keys'] ?? [];

        // A camera added to an existing device is a single asset, so it does
        // not send the per-camera rows of the device form.
        $path = $this->input('category_path');
        $isCamera = is_array($path) && AssetSheetHelper::pathIsCamera($sheet, $path);

        $rules['cameras'] = $isCamera
            ? ['nullable', 'array']
            : ['required', 'array', 'min:1'];
        $rules['cameras.*'] = ['array'];

        foreach ($keys as $key) {
            $rules['cameras.*.'.$key] = [
                'nullable',
                'string',
                $key === 'catatan' ? 'max:1000' : 'max:255',
            ];
        }

        return $rules;
    }
}
