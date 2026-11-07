<?php

declare(strict_types=1);

namespace App\Modules\Roles\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoleRequest extends FormRequest
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
        $roleId = $this->route('id') ?? $this->route('role');

        return [
            'name' => [
                'required',
                'string',
                'max:125',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('roles', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($roleId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:255',
            ],
            'permissions' => [
                'sometimes',
                'array',
            ],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'slug')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'El formato del nombre del rol es inválido.',
            'name.unique' => 'El nombre del rol ya existe.',
            'permissions.*.exists' => 'Uno o más permisos seleccionados no existen.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user && ! $user->hasRole('admin') && $this->has('permissions')) {
                /** @var array<int, string> $inputPerms */
                $inputPerms = (array) $this->input('permissions', []);
                $assignedPerms = collect($inputPerms);
                $userPerms = $user->getAllPermissions()->pluck('slug');
                $diff = $assignedPerms->diff($userPerms);

                if ($diff->isNotEmpty()) {
                    $validator->errors()->add('permissions', 'No puede asignar permisos que no posee.');
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
        $description = isset($data['description']) && trim((string) $data['description']) !== ''
            ? trim((string) $data['description'])
            : null;

        return [
            'name' => $name,
            'description' => $description,
        ];
    }

    /**
     * Retorna los permisos si fueron proporcionados en el payload.
     *
     * @return list<string>|null
     */
    public function permissions(): ?array
    {
        if (! $this->has('permissions')) {
            return null;
        }

        /** @var list<string> $perms */
        $perms = array_values((array) $this->input('permissions'));

        return $perms;
    }
}
