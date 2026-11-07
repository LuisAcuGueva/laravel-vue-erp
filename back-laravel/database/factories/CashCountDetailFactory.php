<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Finance\Models\CashCountDetail;
use App\Modules\Finance\Models\CashSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashCountDetail>
 */
class CashCountDetailFactory extends Factory
{
    protected $model = CashCountDetail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $denomination = 100.00;
        $quantity = 2;

        return [
            'cash_session_id' => CashSession::factory(),
            'denomination' => $denomination,
            'quantity' => $quantity,
            'subtotal' => $denomination * $quantity,
        ];
    }
}
