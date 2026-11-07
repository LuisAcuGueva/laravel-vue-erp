<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Finance;

use App\Modules\Finance\Support\AccountCode;
use PHPUnit\Framework\TestCase;

class AccountCodeTest extends TestCase
{
    public function test_is_valid_accepts_two_to_eight_digits_only(): void
    {
        $this->assertTrue(AccountCode::isValid('10'));
        $this->assertTrue(AccountCode::isValid('12345678'));
        $this->assertFalse(AccountCode::isValid('10.1'));
        $this->assertFalse(AccountCode::isValid('1'));
        $this->assertFalse(AccountCode::isValid('123456789'));
        $this->assertFalse(AccountCode::isValid('1a'));
    }

    public function test_child_must_start_with_parent_code_and_be_longer(): void
    {
        $this->assertTrue(AccountCode::isValidChildOf('101', '10'));
        $this->assertFalse(AccountCode::isValidChildOf('20', '10'));
        $this->assertFalse(AccountCode::isValidChildOf('10', '10'));
    }

    public function test_root_must_have_two_digits(): void
    {
        $this->assertTrue(AccountCode::isValidChildOf('10', null));
        $this->assertFalse(AccountCode::isValidChildOf('101', null));
    }

    public function test_parent_of_returns_longest_proper_prefix(): void
    {
        $codes = ['63', '636', '6361', '65', '659'];

        $this->assertSame('636', AccountCode::parentOf('6361', $codes));
        $this->assertSame('63', AccountCode::parentOf('636', $codes));
        $this->assertNull(AccountCode::parentOf('63', $codes));
    }
}
