<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property string $id
 * @property string $legal_name
 * @property string|null $trade_name
 * @property string|null $legal_representative
 * @property string|null $ruc
 * @property string|null $address
 * @property string|null $ubigeo
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $logo_path
 * @property-read string|null $logo_url
 * @property string $default_currency_code
 * @property string $default_tax_rate
 * @property bool $prices_include_tax
 * @property string|null $terms_conditions
 * @property string|null $warranty_policy
 * @property array<string, mixed>|null $social_links
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CompanySetting extends Model
{
    use HasUuids, LogsActivity;

    protected $table = 'company_settings';

    protected $keyType = 'string';

    public $incrementing = false;

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
        'legal_name',
        'trade_name',
        'legal_representative',
        'ruc',
        'address',
        'ubigeo',
        'phone',
        'email',
        'website',
        'logo_path',
        'default_currency_code',
        'default_tax_rate',
        'prices_include_tax',
        'terms_conditions',
        'warranty_policy',
        'social_links',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_tax_rate' => 'decimal:2',
            'prices_include_tax' => 'boolean',
            'social_links' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Accessor para la URL pública del logo.
     *
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
        );
    }
}
