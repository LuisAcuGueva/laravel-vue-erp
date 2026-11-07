<?php

declare(strict_types=1);

namespace App\Support\Audit;

class AuditContext
{
    private ?string $branchId = null;

    private ?string $sessionId = null;

    public function setBranchId(?string $branchId): void
    {
        $this->branchId = $branchId;
    }

    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function branchId(): ?string
    {
        return $this->branchId;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }
}
