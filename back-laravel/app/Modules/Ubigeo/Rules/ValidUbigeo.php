<?php

declare(strict_types=1);

namespace App\Modules\Ubigeo\Rules;

use App\Modules\Ubigeo\Services\UbigeoService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Acepta solo códigos de distrito vigentes del catálogo INEI.
 * Un código no vigente se acepta si es el que ya tenía el registro (`$current`).
 */
class ValidUbigeo implements ValidationRule
{
    public function __construct(
        private readonly ?string $current = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $service = app(UbigeoService::class);

        $valid = is_string($value)
            && preg_match('/^\d{6}$/', $value) === 1
            && ($service->exists($value) || ($value === $this->current && $service->exists($value, activeOnly: false)));

        if (! $valid) {
            $fail('El ubigeo seleccionado no es válido.');
        }
    }
}
