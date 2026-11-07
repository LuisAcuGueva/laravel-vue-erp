<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaRequest extends FormRequest
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
        /** @var string|null $id */
        $id = $this->route('id');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('areas', 'name')
                    ->ignore($id)
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
