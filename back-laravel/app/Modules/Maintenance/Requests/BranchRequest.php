<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Requests;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Ubigeo\Rules\ValidUbigeo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge([
                'code' => strtoupper(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var string|null $id */
        $id = $this->route('id');

        return [
            'code' => [
                'required',
                'string',
                'max:10',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('branches', 'code')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('branches', 'name')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'ubigeo' => [
                'nullable',
                'string',
                new ValidUbigeo($id !== null ? Branch::query()->find($id)?->ubigeo : null),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'email' => [
                'nullable',
                'email',
                'max:150',
            ],
            'is_main' => [
                'sometimes',
                'boolean',
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
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
        ];

        if (array_key_exists('address', $data)) {
            $attrs['address'] = $data['address'] !== null && trim((string) $data['address']) !== ''
                ? trim((string) $data['address'])
                : null;
        }

        if (array_key_exists('ubigeo', $data)) {
            $attrs['ubigeo'] = $data['ubigeo'] !== null && trim((string) $data['ubigeo']) !== ''
                ? trim((string) $data['ubigeo'])
                : null;
        }

        if (array_key_exists('phone', $data)) {
            $attrs['phone'] = $data['phone'] !== null && trim((string) $data['phone']) !== ''
                ? trim((string) $data['phone'])
                : null;
        }

        if (array_key_exists('email', $data)) {
            $attrs['email'] = $data['email'] !== null && trim((string) $data['email']) !== ''
                ? trim((string) $data['email'])
                : null;
        }

        if (array_key_exists('is_main', $data)) {
            $attrs['is_main'] = (bool) $data['is_main'];
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
