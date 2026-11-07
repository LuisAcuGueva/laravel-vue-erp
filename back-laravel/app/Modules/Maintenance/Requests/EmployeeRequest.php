<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Requests;

use App\Support\Identity\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
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
        $employeeId = $this->route('id') ?? $this->route('employee');
        $docTypeInput = (string) $this->input('document_type');
        $docType = DocumentType::tryFrom($docTypeInput);

        $docNumberRules = ['required', 'string'];
        if ($docType !== null) {
            $docNumberRules = array_merge($docNumberRules, $docType->rulesFor());
        }

        $docNumberRules[] = Rule::unique('employees', 'document_number')
            ->where('document_type', $docTypeInput)
            ->whereNull('deleted_at')
            ->ignore($employeeId);

        return [
            'document_type' => [
                'required',
                Rule::enum(DocumentType::class)->only(DocumentType::forPersons()),
            ],
            'document_number' => $docNumberRules,
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'nullable',
                'string',
                'email',
                'max:150',
                Rule::unique('employees', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($employeeId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'hire_date' => ['nullable', 'date'],
            'position' => ['nullable', 'string', 'max:100'],
            'area_id' => [
                'nullable',
                'uuid',
                Rule::exists('areas', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'branch_id' => [
                'nullable',
                'uuid',
                Rule::exists('branches', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_number.unique' => 'El número de documento ya está registrado para este tipo de documento.',
            'email.unique' => 'El correo electrónico ya se encuentra registrado.',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'area_id.exists' => 'El área seleccionada no existe o está inactiva.',
            'branch_id.exists' => 'La sede seleccionada no existe o está inactiva.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $data = $this->validated();

        $attributes = [
            'document_type' => $data['document_type'],
            'document_number' => trim((string) $data['document_number']),
            'first_name' => mb_convert_case(trim((string) $data['first_name']), MB_CASE_TITLE, 'UTF-8'),
            'last_name' => mb_convert_case(trim((string) $data['last_name']), MB_CASE_TITLE, 'UTF-8'),
            'email' => isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null,
            'phone' => isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
            'address' => isset($data['address']) && trim((string) $data['address']) !== '' ? trim((string) $data['address']) : null,
            'birth_date' => $data['birth_date'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'position' => isset($data['position']) && trim((string) $data['position']) !== '' ? trim((string) $data['position']) : null,
            'area_id' => $data['area_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
        ];

        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) $data['is_active'];
        }

        return $attributes;
    }
}
