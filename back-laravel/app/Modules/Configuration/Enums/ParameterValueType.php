<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Enums;

enum ParameterValueType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Json = 'json';

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::String => ['string', 'max:1000'],
            self::Integer => ['integer'],
            self::Decimal => ['numeric'],
            self::Boolean => ['boolean'],
            self::Json => ['array'],
        };
    }

    /**
     * Castea un valor crudo a su tipo PHP nativo correspondiente.
     */
    public function cast(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($this) {
            self::String => (string) $raw,
            self::Integer => (int) $raw,
            self::Decimal => round((float) $raw, 4),
            self::Boolean => is_bool($raw) ? $raw : filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            self::Json => is_array($raw) ? $raw : (is_string($raw) ? (json_decode($raw, true) ?? $raw) : (array) $raw),
        };
    }
}
