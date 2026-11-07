<?php

declare(strict_types=1);

namespace App\Modules\Ubigeo\Services;

use App\Modules\Ubigeo\Data\UbigeoData;
use App\Modules\Ubigeo\Models\Ubigeo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Acceso de solo lectura al catálogo de ubigeos INEI.
 * Es la única puerta de lectura del catálogo para el resto de módulos.
 */
class UbigeoService
{
    private const CACHE_CATALOG = 'ubigeo:catalog';

    private const CACHE_VERSION = 'ubigeo:version';

    /**
     * @var Collection<string, UbigeoData>|null
     */
    private ?Collection $catalog = null;

    private ?string $version = null;

    /**
     * Catálogo completo indexado por `code` y ordenado por `code`.
     *
     * @return Collection<string, UbigeoData>
     */
    public function catalog(): Collection
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        /** @var array<string, array{code: string, department: string, province: string, district: string, is_active: bool}> $rows */
        $rows = Cache::rememberForever(self::CACHE_CATALOG, function (): array {
            return Ubigeo::query()
                ->orderBy('code')
                ->get(['code', 'department', 'province', 'district', 'is_active'])
                ->mapWithKeys(fn (Ubigeo $ubigeo): array => [
                    $ubigeo->code => [
                        'code' => $ubigeo->code,
                        'department' => $ubigeo->department,
                        'province' => $ubigeo->province,
                        'district' => $ubigeo->district,
                        'is_active' => $ubigeo->is_active,
                    ],
                ])
                ->all();
        });

        return $this->catalog = collect($rows)->map(fn (array $row): UbigeoData => new UbigeoData(
            code: $row['code'],
            department: $row['department'],
            province: $row['province'],
            district: $row['district'],
            isActive: $row['is_active'],
        ));
    }

    /**
     * Hash del catálogo; sirve de `ETag` y de `meta.version`.
     */
    public function version(): string
    {
        if ($this->version !== null) {
            return $this->version;
        }

        /** @var string $version */
        $version = Cache::rememberForever(
            self::CACHE_VERSION,
            fn (): string => sha1((string) json_encode($this->catalog()->map(fn (UbigeoData $u): array => $u->toArray())->values()->all())),
        );

        return $this->version = $version;
    }

    public function find(?string $code): ?UbigeoData
    {
        if ($code === null || $code === '') {
            return null;
        }

        return $this->catalog()->get($code);
    }

    public function exists(?string $code, bool $activeOnly = true): bool
    {
        $ubigeo = $this->find($code);

        return $ubigeo !== null && (! $activeOnly || $ubigeo->isActive);
    }

    /**
     * Texto legible para PDFs y exportaciones; "—" si el código es nulo o no existe.
     */
    public function describe(?string $code): string
    {
        return $this->find($code)?->label() ?? '—';
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_CATALOG);
        Cache::forget(self::CACHE_VERSION);
        $this->catalog = null;
        $this->version = null;
    }
}
