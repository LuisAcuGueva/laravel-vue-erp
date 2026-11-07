<?php

declare(strict_types=1);

namespace App\Support\Auth;

use LogicException;

class CurrentSession
{
    private ?string $sessionId = null;

    public function set(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function has(): bool
    {
        return $this->sessionId !== null;
    }

    public function id(): string
    {
        if ($this->sessionId === null) {
            throw new LogicException('CurrentSession no ha sido resuelta para esta petición.');
        }

        return $this->sessionId;
    }
}
