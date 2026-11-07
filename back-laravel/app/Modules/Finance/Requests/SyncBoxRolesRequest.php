<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncBoxRolesRequest extends FormRequest
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
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['string', 'uuid', Rule::exists('roles', 'id')],
        ];
    }

    /**
     * @return list<string>
     */
    public function roleIds(): array
    {
        /** @var list<string> $roleIds */
        $roleIds = (array) $this->validated('role_ids');

        return array_values($roleIds);
    }
}
