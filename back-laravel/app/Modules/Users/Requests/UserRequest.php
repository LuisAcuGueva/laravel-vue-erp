<?php

declare(strict_types=1);

namespace App\Modules\Users\Requests;

use App\Modules\Roles\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
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
        $isPost = $this->isMethod('post');
        $userId = $this->route('id') ?? $this->route('user');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($userId),
            ],
            'password' => $isPost
                ? ['required', 'string', 'min:8']
                : ['nullable', 'string', 'min:8'],
            'status' => [
                'sometimes',
                'string',
                Rule::in(['active', 'inactive']),
            ],
            'roles' => $isPost
                ? ['required', 'array', 'min:1']
                : ['sometimes', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'El correo electrónico ya se encuentra registrado.',
            'roles.*.exists' => 'Uno o más roles seleccionados no existen.',
            'roles.min' => 'Debe asignar al menos un rol al usuario.',
            'roles.required' => 'Debe asignar al menos un rol al usuario.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $actor = $this->user();

            if ($actor && ! $actor->hasRole('admin') && $this->has('roles')) {
                $requestedRoles = Role::whereIn('name', (array) $this->input('roles', []))
                    ->with('permissions')
                    ->get();

                $allRolePerms = $requestedRoles->flatMap->permissions->pluck('slug')->unique();
                $actorPerms = $actor->getAllPermissions()->pluck('slug');
                $diff = $allRolePerms->diff($actorPerms);

                if ($diff->isNotEmpty()) {
                    $validator->errors()->add('roles', 'No puede asignar roles con permisos que no posee.');
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

        $attributes = [
            'name' => trim((string) $data['name']),
            'email' => trim((string) $data['email']),
        ];

        if (isset($data['status'])) {
            $attributes['status'] = $data['status'];
        }

        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        return $attributes;
    }

    /**
     * Retorna la lista de nombres de roles si fueron enviados.
     *
     * @return list<string>|null
     */
    public function roles(): ?array
    {
        return $this->has('roles') ? array_values((array) $this->input('roles')) : null;
    }
}
