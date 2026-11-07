<?php

declare(strict_types=1);

namespace App\Modules\Permissions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PermissionRequest extends FormRequest
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
        $permId = $this->route('id') ?? $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:125',
            ],
            'slug' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/',
                Rule::unique('permissions', 'slug')
                    ->whereNull('deleted_at')
                    ->ignore($permId),
            ],
            'module' => [
                'nullable',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'El formato del slug debe ser modulo.accion en minúsculas.',
            'slug.unique' => 'El slug del permiso ya existe.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $slug = $this->input('slug');
            $module = $this->input('module');

            if ($slug !== null && $module !== null && trim((string) $module) !== '') {
                $slugStr = (string) $slug;
                if (str_contains($slugStr, '.')) {
                    $expectedPrefix = explode('.', $slugStr)[0];
                    if (trim((string) $module) !== $expectedPrefix) {
                        $validator->errors()->add('module', 'El módulo debe coincidir con el prefijo del slug.');
                    }
                }
            }
        });
    }

    /**
     * Normaliza los atributos validados listos para persistir.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $data = $this->validated();
        $name = trim((string) $data['name']);
        $slug = trim((string) $data['slug']);
        $prefix = explode('.', $slug)[0];

        $module = isset($data['module']) && trim((string) $data['module']) !== ''
            ? trim((string) $data['module'])
            : $prefix;

        $description = isset($data['description']) && trim((string) $data['description']) !== ''
            ? trim((string) $data['description'])
            : null;

        return [
            'name' => $name,
            'slug' => $slug,
            'module' => $module,
            'description' => $description,
        ];
    }
}
