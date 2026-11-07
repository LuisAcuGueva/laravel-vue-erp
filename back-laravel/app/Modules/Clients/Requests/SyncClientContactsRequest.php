<?php

declare(strict_types=1);

namespace App\Modules\Clients\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncClientContactsRequest extends FormRequest
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
            'contacts' => ['present', 'array', 'max:20'],
            'contacts.*.id' => ['nullable', 'uuid'],
            'contacts.*.name' => ['required', 'string', 'max:150'],
            'contacts.*.position' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.email' => ['nullable', 'string', 'email', 'max:150'],
            'contacts.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toContacts(): array
    {
        /** @var list<array<string, mixed>> $contacts */
        $contacts = array_values($this->validated()['contacts']);

        return $contacts;
    }
}
