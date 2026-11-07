<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Audit;

use App\Support\Audit\AuditContext;
use PHPUnit\Framework\TestCase;

class AuditContextTest extends TestCase
{
    public function test_defaults_to_null(): void
    {
        $context = new AuditContext;
        $this->assertNull($context->branchId());
        $this->assertNull($context->sessionId());
    }

    public function test_setters_and_getters(): void
    {
        $context = new AuditContext;
        $context->setBranchId('branch-uuid-1234');
        $context->setSessionId('session-uuid-5678');

        $this->assertSame('branch-uuid-1234', $context->branchId());
        $this->assertSame('session-uuid-5678', $context->sessionId());

        $context->setBranchId(null);
        $context->setSessionId(null);

        $this->assertNull($context->branchId());
        $this->assertNull($context->sessionId());
    }
}
