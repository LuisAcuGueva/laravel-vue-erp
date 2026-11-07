<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El modelo User pasó de App\Models a App\Modules\Users\Models. Las columnas
 * polimórficas de Spatie Permission y activitylog guardan el FQCN.
 */
return new class extends Migration
{
    private const OLD_CLASS = 'App\\Models\\User';

    private const NEW_CLASS = 'App\\Modules\\Users\\Models\\User';

    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'model_has_roles' => ['model_type'],
        'model_has_permissions' => ['model_type'],
        'activity_log' => ['subject_type', 'causer_type'],
    ];

    public function up(): void
    {
        $this->rename(self::OLD_CLASS, self::NEW_CLASS);
    }

    public function down(): void
    {
        $this->rename(self::NEW_CLASS, self::OLD_CLASS);
    }

    private function rename(string $from, string $to): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }
};
