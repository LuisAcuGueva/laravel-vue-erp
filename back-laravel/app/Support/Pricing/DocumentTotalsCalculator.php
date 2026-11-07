<?php

declare(strict_types=1);

namespace App\Support\Pricing;

use App\Modules\Inventory\Enums\TaxType;
use InvalidArgumentException;

/**
 * Calculadora de totales de documentos comerciales (OC, facturas, cotizaciones, ventas).
 *
 * Es pura (sin BD) y usa bcmath con escala interna 6; el redondeo es ROUND_HALF_UP a 2 decimales.
 * Los totales del documento se redondean sobre la suma sin redondear de las líneas y la diferencia
 * se absorbe en la última línea del tipo correspondiente, de modo que la suma de líneas cuadra.
 */
final class DocumentTotalsCalculator
{
    private const SCALE = 6;

    private const OUT_SCALE = 2;

    /**
     * @param  list<LineInput>  $lines
     * @param  string  $taxRate  Porcentaje (ej. '18').
     * @param  string  $globalDiscountType  'amount' o 'percent'; se prorratea por línea antes del impuesto.
     */
    public function calculate(
        array $lines,
        string $taxRate,
        bool $pricesIncludeTax,
        string $globalDiscount = '0',
        string $globalDiscountType = 'amount',
    ): DocumentTotals {
        if (! in_array($globalDiscountType, ['amount', 'percent'], true)) {
            throw new InvalidArgumentException("Tipo de descuento global inválido: {$globalDiscountType}");
        }

        $rate = Decimal::div($taxRate, '100', self::SCALE);

        // 1. Neto por línea tras su propio descuento.
        $nets = [];
        $lineDiscounts = [];
        foreach ($lines as $i => $line) {
            $gross = Decimal::mul($line->quantity, $line->unitPrice, self::SCALE);
            $discount = $line->discountAmount !== null
                ? $line->discountAmount
                : Decimal::div(Decimal::mul($gross, $line->discountPercent, self::SCALE), '100', self::SCALE);
            if (Decimal::cmp($discount, $gross, self::SCALE) > 0) {
                $discount = $gross;
            }
            $lineDiscounts[$i] = $discount;
            $nets[$i] = Decimal::sub($gross, $discount, self::SCALE);
        }

        // 2. Descuento global prorrateado por el neto de cada línea.
        $sumNet = '0';
        foreach ($nets as $net) {
            $sumNet = Decimal::add($sumNet, $net, self::SCALE);
        }

        $globalAmount = $globalDiscountType === 'percent'
            ? Decimal::div(Decimal::mul($sumNet, $globalDiscount, self::SCALE), '100', self::SCALE)
            : $globalDiscount;
        if (Decimal::cmp($globalAmount, $sumNet, self::SCALE) > 0) {
            $globalAmount = $sumNet;
        }

        $remaining = $globalAmount;
        $lastIndex = array_key_last($lines);
        foreach ($lines as $i => $line) {
            $share = $i === $lastIndex || Decimal::cmp($sumNet, '0', self::SCALE) === 0
                ? ($i === $lastIndex ? $remaining : '0')
                : Decimal::div(Decimal::mul($globalAmount, $nets[$i], self::SCALE), $sumNet, self::SCALE);
            $remaining = Decimal::sub($remaining, $share, self::SCALE);
            $nets[$i] = Decimal::sub($nets[$i], $share, self::SCALE);
            $lineDiscounts[$i] = Decimal::add($lineDiscounts[$i], $share, self::SCALE);
        }

        // 3. Base e impuesto por línea (sin redondear).
        $rawSubtotal = [];
        $rawTax = [];
        foreach ($lines as $i => $line) {
            $taxable = $line->taxType === TaxType::Taxable;
            if ($taxable && $pricesIncludeTax) {
                $rawSubtotal[$i] = Decimal::div($nets[$i], Decimal::add('1', $rate, self::SCALE), self::SCALE);
                $rawTax[$i] = Decimal::sub($nets[$i], $rawSubtotal[$i], self::SCALE);
            } else {
                $rawSubtotal[$i] = $nets[$i];
                $rawTax[$i] = $taxable ? Decimal::mul($nets[$i], $rate, self::SCALE) : '0';
            }
        }

        // 4. Líneas redondeadas.
        $subtotals = [];
        $taxes = [];
        $discounts = [];
        foreach ($lines as $i => $line) {
            $subtotals[$i] = $this->round($rawSubtotal[$i]);
            $taxes[$i] = $this->round($rawTax[$i]);
            $discounts[$i] = $this->round($lineDiscounts[$i]);
        }

        // 5. Totales del documento sobre la suma sin redondear, y cuadre en la última línea.
        foreach (TaxType::cases() as $type) {
            $indexes = array_keys(array_filter($lines, fn (LineInput $l): bool => $l->taxType === $type));
            if ($indexes === []) {
                continue;
            }

            $this->reconcile($subtotals, $rawSubtotal, $indexes);

            if ($type === TaxType::Taxable) {
                $this->reconcile($taxes, $rawTax, $indexes);
            }
        }
        $allIndexes = array_keys($lines);
        if ($allIndexes !== []) {
            $this->reconcile($discounts, $lineDiscounts, $allIndexes);
        }

        // 6. Resultado.
        $bases = [TaxType::Taxable->value => '0', TaxType::Exempt->value => '0', TaxType::Unaffected->value => '0'];
        $taxTotal = '0';
        $discountTotal = '0';
        $results = [];
        foreach ($lines as $i => $line) {
            $bases[$line->taxType->value] = Decimal::add($bases[$line->taxType->value], $subtotals[$i], self::OUT_SCALE);
            $taxTotal = Decimal::add($taxTotal, $taxes[$i], self::OUT_SCALE);
            $discountTotal = Decimal::add($discountTotal, $discounts[$i], self::OUT_SCALE);
            $results[] = new LineResult(
                $line->taxType,
                $discounts[$i],
                $subtotals[$i],
                $taxes[$i],
                Decimal::add($subtotals[$i], $taxes[$i], self::OUT_SCALE),
            );
        }

        $total = Decimal::add(Decimal::add(Decimal::add($bases['taxable'], $bases['exempt'], self::OUT_SCALE), $bases['unaffected'], self::OUT_SCALE), $taxTotal, self::OUT_SCALE);

        return new DocumentTotals(
            $bases['taxable'],
            $bases['exempt'],
            $bases['unaffected'],
            $discountTotal,
            $taxTotal,
            $total,
            $results,
        );
    }

    /**
     * Ajusta la última línea del grupo para que la suma redondeada iguale al redondeo de la suma exacta.
     *
     * @param  array<int, string>  $rounded
     * @param  array<int, string>  $raw
     * @param  list<int>  $indexes
     */
    private function reconcile(array &$rounded, array $raw, array $indexes): void
    {
        $exact = '0';
        $sum = '0';
        foreach ($indexes as $i) {
            $exact = Decimal::add($exact, $raw[$i], self::SCALE);
            $sum = Decimal::add($sum, $rounded[$i], self::OUT_SCALE);
        }

        $diff = Decimal::sub($this->round($exact), $sum, self::OUT_SCALE);
        if (Decimal::cmp($diff, '0', self::OUT_SCALE) !== 0) {
            $last = $indexes[count($indexes) - 1];
            $rounded[$last] = Decimal::add($rounded[$last], $diff, self::OUT_SCALE);
        }
    }

    private function round(string $value): string
    {
        $half = '0.'.str_repeat('0', self::OUT_SCALE).'5';

        return Decimal::cmp($value, '0', self::SCALE) >= 0
            ? Decimal::add($value, $half, self::OUT_SCALE)
            : Decimal::sub($value, $half, self::OUT_SCALE);
    }
}
