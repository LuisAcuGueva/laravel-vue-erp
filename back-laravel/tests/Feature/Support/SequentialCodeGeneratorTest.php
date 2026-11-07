<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Support\Numbering\SequentialCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SequentialCodeGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_consecutive_codes_with_padding(): void
    {
        $code1 = SequentialCodeGenerator::next('catalog_item', 'ITM', 6);
        $code2 = SequentialCodeGenerator::next('catalog_item', 'ITM', 6);

        $this->assertSame('ITM-000001', $code1);
        $this->assertSame('ITM-000002', $code2);
    }

    public function test_different_keys_have_independent_sequences(): void
    {
        $item1 = SequentialCodeGenerator::next('catalog_item', 'ITM', 6);
        $note1 = SequentialCodeGenerator::next('warehouse_note', 'NI', 6);
        $item2 = SequentialCodeGenerator::next('catalog_item', 'ITM', 6);

        $this->assertSame('ITM-000001', $item1);
        $this->assertSame('NI-000001', $note1);
        $this->assertSame('ITM-000002', $item2);
    }

    public function test_different_scopes_have_independent_sequences(): void
    {
        $branchA1 = SequentialCodeGenerator::next('warehouse_note', 'NI', 6, 'branch-a');
        $branchB1 = SequentialCodeGenerator::next('warehouse_note', 'NI', 6, 'branch-b');
        $branchA2 = SequentialCodeGenerator::next('warehouse_note', 'NI', 6, 'branch-a');

        $this->assertSame('NI-000001', $branchA1);
        $this->assertSame('NI-000001', $branchB1);
        $this->assertSame('NI-000002', $branchA2);
    }

    public function test_custom_padding_and_null_scope(): void
    {
        $code = SequentialCodeGenerator::next('invoice', 'FAC', 4, null);

        $this->assertSame('FAC-0001', $code);
    }
}
