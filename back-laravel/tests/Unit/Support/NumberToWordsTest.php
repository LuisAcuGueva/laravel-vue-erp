<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Text\NumberToWords;
use PHPUnit\Framework\TestCase;

class NumberToWordsTest extends TestCase
{
    public function test_converts_integers_to_words(): void
    {
        $this->assertSame('CERO', NumberToWords::toWords(0));
        $this->assertSame('UNO', NumberToWords::toWords(1));
        $this->assertSame('QUINCE', NumberToWords::toWords(15));
        $this->assertSame('VEINTIUNO', NumberToWords::toWords(21));
        $this->assertSame('CIEN', NumberToWords::toWords(100));
        $this->assertSame('CIENTO UNO', NumberToWords::toWords(101));
        $this->assertSame('MIL', NumberToWords::toWords(1000));
        $this->assertSame('UN MILLÓN', NumberToWords::toWords(1000000));
        $this->assertSame('DOS MILLONES', NumberToWords::toWords(2000000));
    }

    public function test_to_invoice_text_without_prefix(): void
    {
        $this->assertSame('CIENTO VEINTE CON 50/100 SOLES', NumberToWords::toInvoiceText('120.50', 'PEN', prefix: false));
        $this->assertSame('CIENTO VEINTE CON 50/100 SOLES', NumberToWords::toInvoiceText(120.5, 'SOLES', prefix: false));
    }

    public function test_to_invoice_text_with_prefix(): void
    {
        $this->assertSame('SON: CIENTO VEINTE CON 50/100 SOLES', NumberToWords::toInvoiceText('120.50', 'PEN'));
        $this->assertSame('SON: MIL QUINIENTOS CON 00/100 SOLES', NumberToWords::toInvoiceText('1500.00', 'PEN'));
        $this->assertSame('SON: CIENTO VEINTIÚN CON 00/100 SOLES', NumberToWords::toInvoiceText('121.00', 'PEN'));
        $this->assertSame('SON: DOSCIENTOS CON 25/100 DÓLARES AMERICANOS', NumberToWords::toInvoiceText('200.25', 'USD'));
    }
}
