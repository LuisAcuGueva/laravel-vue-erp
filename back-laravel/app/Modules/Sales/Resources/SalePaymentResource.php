<?php

declare(strict_types=1);

namespace App\Modules\Sales\Resources;

use App\Modules\Finance\Enums\PaymentMethodKind;
use App\Modules\Sales\Models\SalePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalePayment
 */
class SalePaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'payment_method_id' => $this->payment_method_id,
            'payment_method_name' => $this->paymentMethod?->name,
            'payment_method_code' => $this->paymentMethod?->code,
            'is_cash' => $this->paymentMethod?->kind === PaymentMethodKind::Cash,
            'cash_session_id' => $this->cash_session_id,
            'box_name' => $this->cashSession?->box?->name,
            'amount' => $this->amount,
            'received_amount' => $this->received_amount,
            'change_amount' => $this->change_amount,
            'reference' => $this->reference,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
