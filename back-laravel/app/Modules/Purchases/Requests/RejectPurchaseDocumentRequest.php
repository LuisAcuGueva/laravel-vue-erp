<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Motivo obligatorio para rechazar, anular o cerrar un documento de compras.
 */
class RejectPurchaseDocumentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return trim((string) $this->validated('reason'));
    }
}
