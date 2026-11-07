<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Sales\Exceptions\DiscountLimitExceededException;
use App\Modules\Users\Models\User;

class DiscountPolicyService
{
    public function __construct(
        private readonly ?SystemParameterService $parameterService = null,
    ) {}

    private function getParameterService(): SystemParameterService
    {
        return $this->parameterService ?? app(SystemParameterService::class);
    }

    /**
     * Calcula el porcentaje de descuento efectivo combinando el porcentaje explícito
     * y la reducción del precio unitario respecto al precio de lista.
     */
    public function calculateEffectiveDiscount(
        float|string|null $listPrice,
        float|string $unitPrice,
        float|string $discountPercent = 0
    ): float {
        if ($listPrice === null || (float) $listPrice <= 0.0) {
            return 0.0;
        }

        $lp = (float) $listPrice;
        $up = (float) $unitPrice;
        $dp = (float) $discountPercent;

        $netUnit = $up * (1.0 - ($dp / 100.0));
        $effectiveRatio = 1.0 - ($netUnit / $lp);
        $effectivePercent = round($effectiveRatio * 100.0, 4);

        return $effectivePercent > 0.0 ? $effectivePercent : 0.0;
    }

    /**
     * Valida los descuentos por línea y global contra los topes configurados.
     * Si alguno excede el tope y el usuario no tiene permiso 'sales.discount_override',
     * lanza DiscountLimitExceededException. Si tiene permiso, retorna el User para auditoría.
     * Si ningún descuento excede el tope, retorna null.
     *
     * @param  list<array<string, mixed>>  $lines
     *
     * @throws DiscountLimitExceededException
     */
    public function validate(
        array $lines,
        string|float $globalDiscountPercent,
        User $user
    ): ?User {
        $paramService = $this->getParameterService();
        $maxLineDiscount = $paramService->decimal('sales.max_line_discount_percent', 10.0);
        $maxGlobalDiscount = $paramService->decimal('sales.max_global_discount_percent', 10.0);

        /** @var array<string, list<string>> $errors */
        $errors = [];

        foreach ($lines as $i => $line) {
            $listPrice = $line['list_price'] ?? null;
            if ($listPrice === null || (float) $listPrice <= 0.0) {
                // Las líneas sin precio de lista (ej. texto libre) no se validan contra el tope
                continue;
            }

            $unitPrice = $line['unit_price'] ?? 0;
            $discountPercent = $line['discount_percent'] ?? 0;

            $effectivePercent = $this->calculateEffectiveDiscount($listPrice, $unitPrice, $discountPercent);

            if ($effectivePercent > $maxLineDiscount) {
                $errors["items.{$i}.discount_percent"][] = sprintf(
                    'El descuento efectivo de la línea %d es %.2f %% y el máximo es %.2f %%.',
                    $i + 1,
                    $effectivePercent,
                    $maxLineDiscount
                );
            }
        }

        $globalPercent = (float) $globalDiscountPercent;
        if ($globalPercent > $maxGlobalDiscount) {
            $errors['global_discount_percent'][] = sprintf(
                'El descuento global es %.2f %% y el máximo permitido es %.2f %%.',
                $globalPercent,
                $maxGlobalDiscount
            );
        }

        if (! empty($errors)) {
            if (! $user->can('sales.discount_override')) {
                throw new DiscountLimitExceededException(
                    sprintf('El descuento supera el máximo permitido (%.2f %%).', $maxGlobalDiscount),
                    $errors
                );
            }

            return $user;
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return app(self::class)->$name(...$arguments);
    }
}
