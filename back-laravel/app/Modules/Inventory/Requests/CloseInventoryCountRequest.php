<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseInventoryCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'treat_uncounted_as_zero' => ['nullable', 'boolean'],
        ];
    }

    public function treatUncountedAsZero(): bool
    {
        return $this->boolean('treat_uncounted_as_zero');
    }
}
