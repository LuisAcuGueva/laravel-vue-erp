<?php

declare(strict_types=1);

namespace App\Modules\Auth\Exceptions;

class ExpiredTokenException extends \RuntimeException
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        string $message = 'Token expirado.',
        private readonly array $rawPayload = [],
        int $code = 401,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function rawPayload(): array
    {
        return $this->rawPayload;
    }
}
