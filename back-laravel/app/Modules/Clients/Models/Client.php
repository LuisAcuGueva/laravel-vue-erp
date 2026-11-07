<?php

declare(strict_types=1);

namespace App\Modules\Clients\Models;

use App\Modules\Clients\Enums\ClientType;
use App\Modules\Inventory\Enums\PriceType;
use App\Support\Identity\DocumentType;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property ClientType $client_type
 * @property DocumentType|null $document_type
 * @property string|null $document_number
 * @property string $name
 * @property string|null $trade_name
 * @property string|null $representative_name
 * @property string|null $representative_document
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $ubigeo
 * @property PriceType $default_price_type
 * @property string $credit_limit
 * @property int $credit_days
 * @property bool $is_system
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, ClientContact> $contacts
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'clients';

    private static ?Client $cachedSystemClient = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'client_type' => 'person',
        'default_price_type' => 'retail',
        'credit_limit' => 0,
        'credit_days' => 0,
        'is_system' => false,
        'is_active' => true,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_type',
        'document_type',
        'document_number',
        'name',
        'trade_name',
        'representative_name',
        'representative_document',
        'email',
        'phone',
        'address',
        'ubigeo',
        'default_price_type',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_type' => ClientType::class,
            'document_type' => DocumentType::class,
            'default_price_type' => PriceType::class,
            'credit_limit' => 'decimal:2',
            'credit_days' => 'integer',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ClientContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class, 'client_id');
    }

    /**
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<Client>  $query
     * @return Builder<Client>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();
        $operator = $driver === 'pgsql' ? 'ilike' : 'like';
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        return $query->where(function (Builder $inner) use ($like, $operator) {
            $inner->where('name', $operator, $like)
                ->orWhere('trade_name', $operator, $like)
                ->orWhere('document_number', $operator, $like)
                ->orWhere('email', $operator, $like)
                ->orWhere('description', $operator, $like);
        });
    }

    public static function system(): self
    {
        if (self::$cachedSystemClient === null) {
            self::$cachedSystemClient = self::where('is_system', true)->firstOrFail();
        }

        return self::$cachedSystemClient;
    }

    public static function forgetSystemClientCache(): void
    {
        self::$cachedSystemClient = null;
    }

    /**
     * Crea una nueva instancia de factory para este modelo.
     */
    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }
}
