<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Auth;

use App\Support\Auth\CurrentSession;
use LogicException;
use PHPUnit\Framework\TestCase;

class CurrentSessionTest extends TestCase
{
    public function test_has_returns_false_initially(): void
    {
        $session = new CurrentSession;
        $this->assertFalse($session->has());
    }

    public function test_id_throws_logic_exception_when_not_set(): void
    {
        $session = new CurrentSession;
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('CurrentSession no ha sido resuelta para esta petición.');
        $session->id();
    }

    public function test_set_and_id_work_as_expected(): void
    {
        $session = new CurrentSession;
        $session->set('018e3333-0000-7000-8000-000000000001');

        $this->assertTrue($session->has());
        $this->assertSame('018e3333-0000-7000-8000-000000000001', $session->id());
    }
}
