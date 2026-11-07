<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Requests;

use App\Modules\Roles\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateEmployeeUserRequest extends FormRequest
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
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'password_confirmation' => [
                'required_with:password',
                'string',
            ],
            'roles' => [
                'required',
                'array',
                'min:1',
            ],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->whereNull('deleted_at'),
            ],
            'branch_ids' => [
                'nullable',
                'array',
            ],
            'branch_ids.*' => [
                'uuid',
                Rule::exists('branches', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
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
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'branch_ids.*.exists' => 'Una o más sedes seleccionadas no existen o están inactivas.',
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
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        $data = $this->validated();

        return [
            'email' => trim((string) $data['email']),
            'password' => $data['password'],
        ];
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return array_values((array) $this->input('roles', []));
    }

    /**
     * @return list<string>|null
     */
    public function branchIds(): ?array
    {
        if (! $this->has('branch_ids')) {
            return null;
        }

        $branchIds = $this->input('branch_ids');

        return is_array($branchIds) ? array_values($branchIds) : null;
    }
}
