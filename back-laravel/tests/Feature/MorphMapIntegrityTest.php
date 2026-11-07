<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\Activitylog\Traits\LogsActivity;
use Tests\TestCase;

class MorphMapIntegrityTest extends TestCase
{
    public function test_morph_map_has_no_duplicate_classes(): void
    {
        $map = Relation::morphMap();
        $this->assertNotEmpty($map, 'Relation::morphMap() no debería estar vacío.');

        // Verifica que ninguna clase tenga más de un alias asignado (detecta el caso CashSession/cash_session)
        $classCounts = array_count_values(array_map('strval', $map));
        $duplicates = array_filter($classCounts, fn (int $count): bool => $count > 1);

        $this->assertEmpty(
            $duplicates,
            'Relation::morphMap() contiene clases duplicadas con múltiples alias: '.json_encode($duplicates)
        );
    }

    public function test_all_logs_activity_models_have_morph_map_aliases(): void
    {
        $map = Relation::morphMap();
        $models = $this->discoverLogsActivityModels();

        $this->assertNotEmpty(
            $models,
            'El escaneo de app/Modules/*/Models no encontró ningún modelo con LogsActivity; revisar discoverLogsActivityModels().'
        );

        $missing = [];
        foreach ($models as $modelClass) {
            $alias = array_search($modelClass, $map, true);

            if ($alias === false) {
                $missing[] = $modelClass;

                continue;
            }

            $this->assertMatchesRegularExpression(
                '/^[a-z0-9_]+$/',
                (string) $alias,
                "El alias '{$alias}' para {$modelClass} debe ser snake_case."
            );
        }

        $this->assertEmpty(
            $missing,
            'Modelos con `use LogsActivity` sin alias registrado en Relation::morphMap(): '.implode(', ', $missing)
        );
    }

    /**
     * Escanea app/Modules/<Modulo>/Models/*.php y devuelve las clases que usan LogsActivity.
     *
     * @return list<string>
     */
    private function discoverLogsActivityModels(): array
    {
        $models = [];

        foreach (glob(app_path('Modules').'/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $moduleName = basename($moduleDir);
            $modelsPath = $moduleDir.'/Models';

            if (! is_dir($modelsPath)) {
                continue;
            }

            foreach (glob($modelsPath.'/*.php') ?: [] as $file) {
                $className = basename($file, '.php');
                $fqcn = "App\\Modules\\{$moduleName}\\Models\\{$className}";

                if (! class_exists($fqcn) || ! is_subclass_of($fqcn, Model::class)) {
                    continue;
                }

                if (in_array(LogsActivity::class, class_uses_recursive($fqcn), true)) {
                    $models[] = $fqcn;
                }
            }
        }

        return $models;
    }

    public function test_all_morph_map_keys_are_snake_case(): void
    {
        $map = Relation::morphMap();

        foreach ($map as $alias => $class) {
            $this->assertMatchesRegularExpression(
                '/^[a-z0-9_]+$/',
                (string) $alias,
                "La clave '{$alias}' en morphMap para la clase {$class} debe ser snake_case sin mayúsculas."
            );
        }
    }
}
