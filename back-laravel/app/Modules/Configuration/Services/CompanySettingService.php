<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Services;

use App\Modules\Configuration\Models\CompanySetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanySettingService
{
    /**
     * Valores por defecto para inicialización perezosa de la empresa.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'default_currency_code' => 'PEN',
        'default_tax_rate' => 18.00,
        'prices_include_tax' => true,
    ];

    /**
     * Obtiene el registro único de configuración de empresa, creándolo de forma perezosa si no existe.
     *
     * La unicidad la garantiza el índice único de `company_settings.singleton`: si dos peticiones
     * crean el registro a la vez, `firstOrCreate` (vía `createOrFirst`) captura la violación y
     * devuelve la fila ya insertada.
     */
    public function current(): CompanySetting
    {
        return CompanySetting::query()->firstOrCreate([], array_merge(self::DEFAULTS, [
            'legal_name' => (string) config('app.name', 'Template ERP'),
        ]));
    }

    /**
     * Actualiza los datos de la empresa.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): CompanySetting
    {
        $company = $this->current();
        $company->update($attributes);

        return $company->fresh() ?? $company;
    }

    /**
     * Reemplaza el logo de la empresa en el disco público y elimina el archivo anterior.
     */
    public function replaceLogo(UploadedFile $file): CompanySetting
    {
        $company = $this->current();

        if ($company->logo_path !== null && Storage::disk('public')->exists($company->logo_path)) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $path = $file->store('company', 'public');
        $company->update(['logo_path' => $path]);

        return $company->fresh() ?? $company;
    }

    public function setDefaultCurrency(string $code): CompanySetting
    {
        $company = $this->current();
        $company->update(['default_currency_code' => $code]);

        return $company->fresh() ?? $company;
    }
}
