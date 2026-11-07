<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Modules\Inventory\Enums\TaxType;
use App\Support\Pricing\DocumentTotalsCalculator;
use App\Support\Pricing\LineInput;
use PHPUnit\Framework\TestCase;

class DocumentTotalsCalculatorTest extends TestCase
{
    private DocumentTotalsCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new DocumentTotalsCalculator;
    }

    public function test_prices_without_tax_add_tax_on_top(): void
    {
        $totals = $this->calculator->calculate([new LineInput('10', '100')], '18', false);

        $this->assertSame('1000.00', $totals->taxableBase);
        $this->assertSame('180.00', $totals->taxTotal);
        $this->assertSame('1180.00', $totals->total);
        $this->assertSame('0.00', $totals->discountTotal);
        $this->assertSame('1180.00', $totals->lines[0]->total);
    }

    public function test_prices_including_tax_extract_the_base(): void
    {
        $totals = $this->calculator->calculate([new LineInput('1', '118')], '18', true);

        $this->assertSame('100.00', $totals->taxableBase);
        $this->assertSame('18.00', $totals->taxTotal);
        $this->assertSame('118.00', $totals->total);
    }

    public function test_exempt_and_unaffected_lines_carry_no_tax(): void
    {
        $totals = $this->calculator->calculate([
            new LineInput('1', '100', taxType: TaxType::Taxable),
            new LineInput('1', '50', taxType: TaxType::Exempt),
            new LineInput('2', '25', taxType: TaxType::Unaffected),
        ], '18', false);

        $this->assertSame('100.00', $totals->taxableBase);
        $this->assertSame('50.00', $totals->exemptBase);
        $this->assertSame('50.00', $totals->unaffectedBase);
        $this->assertSame('18.00', $totals->taxTotal);
        $this->assertSame('218.00', $totals->total);
        $this->assertSame('0.00', $totals->lines[1]->tax);
    }

    public function test_exempt_line_with_prices_including_tax_is_not_divided(): void
    {
        $totals = $this->calculator->calculate([new LineInput('1', '118', taxType: TaxType::Exempt)], '18', true);

        $this->assertSame('118.00', $totals->exemptBase);
        $this->assertSame('0.00', $totals->taxTotal);
        $this->assertSame('118.00', $totals->total);
    }

    public function test_line_discount_percent_and_amount(): void
    {
        $percent = $this->calculator->calculate([new LineInput('10', '100', discountPercent: '5')], '18', false);
        $this->assertSame('50.00', $percent->discountTotal);
        $this->assertSame('950.00', $percent->taxableBase);
        $this->assertSame('171.00', $percent->taxTotal);
        $this->assertSame('1121.00', $percent->total);

        $amount = $this->calculator->calculate([new LineInput('10', '100', discountAmount: '100')], '18', false);
        $this->assertSame('100.00', $amount->discountTotal);
        $this->assertSame('900.00', $amount->taxableBase);
    }

    public function test_global_discount_is_prorated_across_lines_before_tax(): void
    {
        $totals = $this->calculator->calculate([
            new LineInput('1', '100'),
            new LineInput('1', '300', taxType: TaxType::Exempt),
        ], '18', false, '40');

        // 40 sobre 400 de neto: 10 a la línea gravada y 30 a la exonerada.
        $this->assertSame('90.00', $totals->taxableBase);
        $this->assertSame('270.00', $totals->exemptBase);
        $this->assertSame('16.20', $totals->taxTotal);
        $this->assertSame('40.00', $totals->discountTotal);
        $this->assertSame('376.20', $totals->total);
    }

    public function test_global_discount_percent(): void
    {
        $totals = $this->calculator->calculate([new LineInput('1', '200')], '18', false, '10', 'percent');

        $this->assertSame('180.00', $totals->taxableBase);
        $this->assertSame('20.00', $totals->discountTotal);
        $this->assertSame('212.40', $totals->total);
    }

    public function test_rounding_is_reconciled_on_the_last_line(): void
    {
        $lines = [
            new LineInput('1', '0.333333'),
            new LineInput('1', '0.333333'),
            new LineInput('1', '0.333333'),
        ];

        $totals = $this->calculator->calculate($lines, '18', false);

        $this->assertSame('1.00', $totals->taxableBase);
        $this->assertSame('0.18', $totals->taxTotal);
        $this->assertSame('1.18', $totals->total);
        $this->assertSame(['0.33', '0.33', '0.34'], array_map(fn ($l) => $l->subtotal, $totals->lines));

        $sumSubtotal = array_sum(array_map(fn ($l) => (float) $l->subtotal, $totals->lines));
        $sumTax = array_sum(array_map(fn ($l) => (float) $l->tax, $totals->lines));
        $this->assertEqualsWithDelta((float) $totals->taxableBase, $sumSubtotal, 0.0001);
        $this->assertEqualsWithDelta((float) $totals->taxTotal, $sumTax, 0.0001);
    }

    public function test_lines_always_add_up_to_the_document_total(): void
    {
        $lines = [
            new LineInput('3', '1.115', discountPercent: '7.5'),
            new LineInput('7', '0.995', taxType: TaxType::Exempt),
            new LineInput('2.5', '19.99'),
            new LineInput('11', '0.0333'),
        ];

        foreach ([false, true] as $includeTax) {
            $totals = $this->calculator->calculate($lines, '18', $includeTax, '1.37');

            $sumLines = '0.00';
            foreach ($totals->lines as $line) {
                $sumLines = bcadd($sumLines, $line->total, 2);
            }

            $this->assertSame($totals->total, $sumLines);
        }
    }

    public function test_half_up_rounding(): void
    {
        $totals = $this->calculator->calculate([new LineInput('1', '0.125')], '0', false);
        $this->assertSame('0.13', $totals->taxableBase);

        $totals = $this->calculator->calculate([new LineInput('1', '0.124')], '0', false);
        $this->assertSame('0.12', $totals->taxableBase);
    }

    public function test_empty_document_has_zero_totals(): void
    {
        $totals = $this->calculator->calculate([], '18', false);

        $this->assertSame('0.00', $totals->total);
        $this->assertSame([], $totals->lines);
    }

    public function test_discount_cannot_exceed_the_gross_amount(): void
    {
        $totals = $this->calculator->calculate([new LineInput('1', '50', discountPercent: '150')], '18', false);

        $this->assertSame('0.00', $totals->total);
        $this->assertSame('50.00', $totals->discountTotal);
    }
}
