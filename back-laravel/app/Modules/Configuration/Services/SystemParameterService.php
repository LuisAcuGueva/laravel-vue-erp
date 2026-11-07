<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Services;

use App\Modules\Configuration\Exceptions\ParameterNotEditableException;
use App\Modules\Configuration\Models\SystemParameter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class SystemParameterService
{
    public const CACHE_KEY = 'system_parameters.all';

    /**
     * Retorna todos los parámetros indexados por su clave compuesta, usando caché persistente.
     *
     * @return array<string, SystemParameter>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            /** @var Collection<int, SystemParameter> $all */
            $all = SystemParameter::all();

            $indexed = [];
            foreach ($all as $param) {
                $indexed[$param->key] = $param;
            }

            return $indexed;
        });
    }

    /**
     * Obtiene el valor casteado de un parámetro por su clave, o el valor por defecto si no existe o está inactivo.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $parameters = $this->all();

        if (! isset($parameters[$key])) {
            return $default;
        }

        $param = $parameters[$key];

        if (! $param->is_active) {
            return $default;
        }

        return $param->value_type->cast($param->value) ?? $default;
    }

    /**
     * Atajo tipado para obtener un valor decimal/flotante.
     */
    public function decimal(string $key, float $default = 0.0): float
    {
        $val = $this->get($key, $default);

        return is_numeric($val) ? (float) $val : $default;
    }

    /**
     * Atajo tipado para obtener un valor entero.
     */
    public function integer(string $key, int $default = 0): int
    {
        $val = $this->get($key, $default);

        return is_numeric($val) ? (int) $val : $default;
    }

    /**
     * Atajo tipado para obtener un valor booleano.
     */
    public function boolean(string $key, bool $default = false): bool
    {
        $val = $this->get($key, $default);

        return is_bool($val) ? $val : (bool) $val;
    }

    /**
     * Atajo tipado para obtener un valor de texto.
     */
    public function string(string $key, string $default = ''): string
    {
        $val = $this->get($key, $default);

        return is_string($val) ? $val : (string) $val;
    }

    /**
     * Lista los parámetros de la base de datos para administración, opcionalmente filtrados por grupo.
     *
     * @return Collection<int, SystemParameter>
     */
    public function list(?string $group = null): Collection
    {
        return SystemParameter::query()
            ->group($group)
            ->orderBy('group_key')
            ->orderBy('item_key')
            ->get();
    }

    /**
     * Busca un parámetro por su ID.
     */
    public function findById(string $id): ?SystemParameter
    {
        return SystemParameter::query()->find($id);
    }

    /**
     * Actualiza el valor de un parámetro existente, invalidando la caché.
     *
     * @throws ParameterNotEditableException
     */
    public function update(SystemParameter $param, mixed $value): SystemParameter
    {
        if (! $param->is_editable) {
            throw new ParameterNotEditableException;
        }

        $param->value = $value;
        $param->save();

        Cache::forget(self::CACHE_KEY);

        return $param->fresh() ?? $param;
    }

    /**
     * Permite invocaciones estáticas a los métodos de instancia (ej. SystemParameterService::decimal(...)).
     *
     * @param  array<int, mixed>  $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return app(self::class)->$name(...$arguments);
    }
}
