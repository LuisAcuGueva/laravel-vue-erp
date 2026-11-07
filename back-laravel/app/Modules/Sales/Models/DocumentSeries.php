<?php

declare(strict_types=1);

namespace App\Modules\Sales\Models;

use App\Modules\Finance\Models\Box;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Enums\SalesDocumentType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $branch_id
 * @property string|null $box_id
 * @property SalesDocumentType $document_type
 * @property string $series
 * @property int $last_number
 * @property bool $is_electronic
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Branch $branch
 * @property-read Box|null $box
 */
class DocumentSeries extends Model
{
    use HasUuids, LogsActivity, SoftDeletes;

    protected $table = 'document_series';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'box_id',
        'document_type',
        'series',
        'last_number',
        'is_electronic',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'last_number' => 0,
        'is_electronic' => false,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => SalesDocumentType::class,
            'last_number' => 'integer',
            'is_electronic' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function format(int $number): string
    {
        return sprintf('%s-%08d', $this->series, $number);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<Box, $this>
     */
    public function box(): BelongsTo
    {
        return $this->belongsTo(Box::class, 'box_id');
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'document_series_id');
    }
}
