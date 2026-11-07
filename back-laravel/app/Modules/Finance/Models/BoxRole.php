<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property string $box_id
 * @property string $role_id
 */
class BoxRole extends Pivot
{
    use HasUuids;

    protected $table = 'box_roles';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'box_id',
        'role_id',
    ];
}
