<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use App\Modules\Maintenance\Services\UserBranchService;
use App\Modules\Users\Models\User;
use App\Support\Branch\ActiveBranch;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoxRequest extends FormRequest
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

        if ($this->isMethod('post') && ! $this->has('branch_id')) {
            $active = app(ActiveBranch::class)->get();
            $this->merge(['branch_id' => $active->id]);
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
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:10',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('boxes', 'code')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:80',
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'branch_id' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
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
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'role_ids' => [
                'sometimes',
                'array',
            ],
            'role_ids.*' => [
                'string',
                'uuid',
                Rule::exists('roles', 'id'),
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

        $attrs = [];

        if (array_key_exists('code', $data)) {
            $attrs['code'] = strtoupper(trim((string) $data['code']));
        }

        if (array_key_exists('name', $data)) {
            $attrs['name'] = trim((string) $data['name']);
        }

        if (array_key_exists('branch_id', $data)) {
            $attrs['branch_id'] = (string) $data['branch_id'];
        }

        if (array_key_exists('description', $data)) {
            $attrs['description'] = $data['description'] !== null && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null;
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        if (array_key_exists('role_ids', $data)) {
            $attrs['role_ids'] = (array) $data['role_ids'];
        }

        return $attrs;
    }
}
