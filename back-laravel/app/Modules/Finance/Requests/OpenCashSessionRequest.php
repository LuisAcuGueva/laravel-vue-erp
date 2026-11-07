<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenCashSessionRequest extends FormRequest
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
            'box_id' => [
                'required',
                'string',
                'uuid',
                Rule::exists('boxes', 'id')->whereNull('deleted_at'),
            ],
            'opening_amount' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
            'opening_notes' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    public function boxId(): string
    {
        return (string) $this->validated('box_id');
    }

    public function openingAmount(): float
    {
        return (float) $this->validated('opening_amount');
    }

    public function openingNotes(): ?string
    {
        $notes = $this->validated('opening_notes');

        return $notes !== null && trim((string) $notes) !== '' ? trim((string) $notes) : null;
    }
}
