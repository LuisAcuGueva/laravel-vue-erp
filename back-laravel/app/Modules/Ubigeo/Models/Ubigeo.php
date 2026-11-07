<?php

declare(strict_types=1);

namespace App\Modules\Ubigeo\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Catálogo INEI de ubigeos (un registro por distrito). Solo lectura para la aplicación:
 * cambia únicamente con `UbigeoSeeder`.
 *
 * @property string $id
 * @property string $code
 * @property string $department_code
 * @property string $province_code
 * @property string $department
 * @property string $province
 * @property string $district
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Ubigeo extends Model
{
    use HasUuids;

    protected $table = 'ubigeos';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'department_code',
        'province_code',
        'department',
        'province',
        'district',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
