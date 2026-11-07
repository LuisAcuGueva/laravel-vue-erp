<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUserBranchesRequest extends FormRequest
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
        /** @var mixed $branchIds */
        $branchIds = $this->input('branch_ids', []);
        $branchIdsArray = is_array($branchIds) ? $branchIds : [];

        return [
            'branch_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'branch_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('branches', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
            ],
            'default_branch_id' => [
                'required',
                'uuid',
                Rule::in($branchIdsArray),
            ],
        ];
    }
}
