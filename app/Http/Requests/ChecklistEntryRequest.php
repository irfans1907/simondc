<?php

namespace App\Http\Requests;

use App\Models\ChecklistCategory;
use App\Models\ChecklistDayNote;
use App\Models\ChecklistEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChecklistEntryRequest extends FormRequest
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
     * Entries are submitted as a sparse list: the daily page only sends the
     * cells the user touched. An entry with no value, status, or note clears
     * the stored record instead of writing an empty one.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('checklist_categories', 'id')],
            'entry_date' => ['required', 'date'],
            'risk_level' => ['required', 'string', Rule::in(ChecklistDayNote::RISK_LEVELS)],
            'note' => ['nullable', 'string', 'max:2000'],
            'entries' => ['present', 'array'],
            'entries.*.item_id' => ['required', 'integer', Rule::exists('checklist_items', 'id')],
            'entries.*.time_slot' => ['required', 'string', 'max:5'],
            'entries.*.value' => ['nullable', 'numeric'],
            'entries.*.status' => ['nullable', 'string', Rule::in(ChecklistEntry::STATUSES)],
            'entries.*.note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Cross-field checks that rules() cannot express: every item must belong
     * to the submitted category, every time slot must be one the category
     * defines, and a filled cell must match the item's input type.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $category = ChecklistCategory::with('items')->find($this->integer('category_id'));

                if (! $category) {
                    return;
                }

                $items = $category->items->keyBy('id');

                foreach ($this->input('entries', []) as $index => $entry) {
                    $item = $items->get($entry['item_id'] ?? null);

                    if (! $item) {
                        $validator->errors()->add(
                            "entries.{$index}.item_id",
                            __('This item does not belong to the selected category.'),
                        );

                        continue;
                    }

                    if (! in_array($entry['time_slot'] ?? null, $category->time_slots, true)) {
                        $validator->errors()->add(
                            "entries.{$index}.time_slot",
                            __('This time slot is not available for the selected category.'),
                        );

                        continue;
                    }

                    $hasValue = filled($entry['value'] ?? null);
                    $hasStatus = filled($entry['status'] ?? null);
                    $hasNote = filled($entry['note'] ?? null);

                    if (! $hasValue && ! $hasStatus && ! $hasNote) {
                        continue;
                    }

                    if ($item->input_type === 'number' && ! $hasValue) {
                        $validator->errors()->add(
                            "entries.{$index}.value",
                            __('A numeric reading is required for :item.', ['item' => $item->label]),
                        );
                    }

                    if ($item->input_type === 'status' && ! $hasStatus) {
                        $validator->errors()->add(
                            "entries.{$index}.status",
                            __('A status is required for :item.', ['item' => $item->label]),
                        );
                    }
                }
            },
        ];
    }
}
