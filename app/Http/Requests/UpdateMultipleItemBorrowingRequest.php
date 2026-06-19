<?php

namespace App\Http\Requests;

use App\Models\ItemBorrowing;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class UpdateMultipleItemBorrowingRequest extends StoreMultipleItemBorrowingRequest
{
    public function authorize(): bool
    {
        $itemBorrowing = $this->route('itemBorrowing');

        return $itemBorrowing instanceof ItemBorrowing
            && $this->user()?->can('update', $itemBorrowing) === true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $itemBorrowing = $this->route('itemBorrowing');

        $rules['attachment'] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'];
        $rules['resubmitted_from_id'] = ['nullable'];
        $rules['items.*.id'] = [
            'nullable',
            Rule::exists('item_borrowing_items', 'id')
                ->where('item_borrowing_id', $itemBorrowing?->id),
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'items.*.id.exists' => 'Detail barang tidak valid untuk pengajuan ini.',
        ];
    }

    protected function minimumBorrowDate(string $timezone): Carbon
    {
        /** @var ItemBorrowing|null $itemBorrowing */
        $itemBorrowing = $this->route('itemBorrowing');
        $originalCutoff = $itemBorrowing?->created_at
            ? $itemBorrowing->created_at->copy()->setTimezone($timezone)->startOfDay()->addDays(7)
            : Carbon::now($timezone)->addDays(7)->startOfDay();

        return $originalCutoff->max(Carbon::now($timezone)->startOfDay());
    }
}
