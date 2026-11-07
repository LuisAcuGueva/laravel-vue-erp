<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Finance\Models\Bank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BankRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('bank');
        if ($id instanceof Bank) {
            $id = $id->id;
        }

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('banks', 'name')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'short_name' => [
                'required',
                'string',
                'max:20',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $attrs = [
            'name' => trim((string) $data['name']),
            'short_name' => strtoupper(trim((string) $data['short_name'])),
        ];

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
