<?php

declare(strict_types=1);

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewCashSessionRequest extends FormRequest
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
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'notes' => ['required_if:decision,rejected', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function decision(): string
    {
        return (string) $this->validated('decision');
    }

    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return $notes !== null && trim((string) $notes) !== '' ? trim((string) $notes) : null;
    }
}
