<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property string $id
 * @property string $user_id
 * @property string $branch_id
 * @property bool $is_default
 */
class UserBranch extends Pivot
{
    use HasUuids;

    protected $table = 'user_branches';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'user_id',
        'branch_id',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
