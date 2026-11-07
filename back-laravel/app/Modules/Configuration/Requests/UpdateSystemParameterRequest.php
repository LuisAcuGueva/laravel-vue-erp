<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Requests;

use App\Modules\Configuration\Models\SystemParameter;
use App\Modules\Configuration\Services\SystemParameterService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemParameterRequest extends FormRequest
{
    private ?SystemParameter $resolvedParam = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $id = (string) $this->route('id');
        $parameterService = app(SystemParameterService::class);
        $this->resolvedParam = $parameterService->findById($id);

        if ($this->resolvedParam === null) {
            return [
                'value' => ['present'],
            ];
        }

        return [
            'value' => [
                'present',
                ...$this->resolvedParam->value_type->rules(),
            ],
        ];
    }

    /**
     * Retorna el valor validado y casteado según el value_type del parámetro.
     */
    public function toValue(): mixed
    {
        if ($this->resolvedParam === null) {
            $id = (string) $this->route('id');
            $this->resolvedParam = app(SystemParameterService::class)->findById($id);
        }

        $raw = $this->input('value');

        return $this->resolvedParam !== null
            ? $this->resolvedParam->value_type->cast($raw)
            : $raw;
    }
}
