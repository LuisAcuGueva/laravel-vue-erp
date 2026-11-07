<?php

declare(strict_types=1);

namespace App\Modules\Ubigeo\Data;

final readonly class UbigeoData
{
    public function __construct(
        public string $code,
        public string $department,
        public string $province,
        public string $district,
        public bool $isActive,
    ) {}

    /**
     * @return array{code: string, department: string, province: string, district: string, is_active: bool}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'department' => $this->department,
            'province' => $this->province,
            'district' => $this->district,
            'is_active' => $this->isActive,
        ];
    }

    /**
     * Texto legible (departamento, provincia, distrito): "LIMA, LIMA, MIRAFLORES".
     */
    public function label(): string
    {
        return "{$this->department}, {$this->province}, {$this->district}";
    }
}
