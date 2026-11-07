<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class KardexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'uuid', 'exists:warehouses,id'],
            'catalog_item_id' => ['required', 'uuid', 'exists:catalog_items,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('date_from') && $this->filled('date_to') && ! $validator->errors()->has('date_to')) {
                    $from = Carbon::parse((string) $this->input('date_from'));
                    $to = Carbon::parse((string) $this->input('date_to'));

                    if ($from->diffInDays($to) > 366) {
                        $validator->errors()->add(
                            'date_to',
                            'El rango de fechas no puede exceder los 366 días.'
                        );
                    }
                }
            },
        ];
    }
}
