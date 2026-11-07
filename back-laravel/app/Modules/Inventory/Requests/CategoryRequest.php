<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('parent_id') && ($this->input('parent_id') === '' || $this->input('parent_id') === null)) {
            $this->merge(['parent_id' => null]);
        }

        if ($this->has('code') && is_string($this->input('code'))) {
            $code = trim((string) $this->input('code'));
            $this->merge(['code' => $code !== '' ? strtoupper($code) : null]);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var string|null $id */
        $id = $this->route('id');
        $parentId = $this->input('parent_id');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')
                    ->where(function (Builder $query) use ($parentId) {
                        if ($parentId !== null && $parentId !== '') {
                            $query->where('parent_id', $parentId);
                        } else {
                            $query->whereNull('parent_id');
                        }

                        return $query->whereNull('deleted_at');
                    })
                    ->ignore($id),
            ],
            'code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('categories', 'code')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'parent_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('categories', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
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
        ];

        if (array_key_exists('code', $data)) {
            $attrs['code'] = $data['code'] !== null && trim((string) $data['code']) !== ''
                ? strtoupper(trim((string) $data['code']))
                : null;
        }

        if (array_key_exists('parent_id', $data)) {
            $attrs['parent_id'] = $data['parent_id'] !== null && trim((string) $data['parent_id']) !== ''
                ? (string) $data['parent_id']
                : null;
        }

        if (array_key_exists('description', $data)) {
            $attrs['description'] = $data['description'] !== null && trim((string) $data['description']) !== ''
                ? trim((string) $data['description'])
                : null;
        }

        if (array_key_exists('is_active', $data)) {
            $attrs['is_active'] = (bool) $data['is_active'];
        }

        return $attrs;
    }
}
