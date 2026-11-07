<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Role y Permission pasaron de App\Modules\Auth\Models a sus módulos
 * (Roles, Permissions). activitylog guarda el FQCN en las columnas polimórficas.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const CLASSES = [
        'App\\Modules\\Auth\\Models\\Role' => 'App\\Modules\\Roles\\Models\\Role',
        'App\\Modules\\Auth\\Models\\Permission' => 'App\\Modules\\Permissions\\Models\\Permission',
    ];

    /** @var list<string> */
    private const COLUMNS = ['subject_type', 'causer_type'];

    public function up(): void
    {
        foreach (self::CLASSES as $from => $to) {
            $this->rename($from, $to);
        }
    }

    public function down(): void
    {
        foreach (self::CLASSES as $from => $to) {
            $this->rename($to, $from);
        }
    }

    private function rename(string $from, string $to): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }

        foreach (self::COLUMNS as $column) {
            DB::table('activity_log')->where($column, $from)->update([$column => $to]);
        }
    }
};
