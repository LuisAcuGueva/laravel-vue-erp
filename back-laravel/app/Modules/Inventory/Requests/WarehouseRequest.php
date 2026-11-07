<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use App\Modules\Maintenance\Services\UserBranchService;
use App\Modules\Users\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
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
                Rule::unique('warehouses', 'code')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'branch_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('branches', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    /** @var User|null $user */
                    $user = $this->user();
                    if ($user !== null) {
                        $accessible = app(UserBranchService::class)->accessibleBranches($user);
                        if (! $accessible->contains('id', (string) $value)) {
                            $fail('La sede seleccionada no es accesible para el usuario.');
                        }
                    }
                },
            ],
            'address' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_default' => [
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
            'branch_id' => (string) $data['branch_id'],
        ];

        if (array_key_exists('address', $data)) {
            $attrs['address'] = $data['address'] !== null && trim((string) $data['address']) !== ''
                ? trim((string) $data['address'])
                : null;
        }

        if (array_key_exists('is_default', $data)) {
            $attrs['is_default'] = (bool) $data['is_default'];
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
