<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'count' => ['required_without:counted_cash', 'array'],
            'count.*.denomination' => ['required_with:count', 'numeric', 'gt:0'],
            'count.*.quantity' => ['required_with:count', 'integer', 'min:0'],
            'counted_cash' => ['required_without:count', 'nullable', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<array{denomination: numeric, quantity: int}>|null
     */
    public function countDetails(): ?array
    {
        /** @var list<array{denomination: numeric, quantity: int}>|null $count */
        $count = $this->validated('count');

        return $count;
    }

    public function countedCash(): ?float
    {
        $cash = $this->validated('counted_cash');

        return $cash !== null ? (float) $cash : null;
    }

    public function closingNotes(): ?string
    {
        $notes = $this->validated('closing_notes');

        return $notes !== null && trim((string) $notes) !== '' ? trim((string) $notes) : null;
    }
}
