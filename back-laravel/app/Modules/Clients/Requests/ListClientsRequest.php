<?php

declare(strict_types=1);

namespace App\Modules\Clients\Requests;

use App\Modules\Clients\Enums\ClientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListClientsRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'client_type' => ['nullable', Rule::enum(ClientType::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
