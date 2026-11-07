<?php

declare(strict_types=1);

namespace App\Modules\Sales\Requests;

use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\DocumentSeries;
use App\Support\Branch\ActiveBranch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DocumentSeriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El alta usa la sede activa; en la edición la sede y el tipo son inmutables y salen del registro.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('post') && ! $this->has('branch_id')) {
            $this->merge(['branch_id' => app(ActiveBranch::class)->get()->id]);
        }

        $current = $this->currentSeries();

        if ($current !== null) {
            $this->merge([
                'branch_id' => $current->branch_id,
                'document_type' => $current->document_type->value,
                'series' => $this->input('series', $current->series),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $seriesId = $this->currentSeries()?->id;

        $documentType = $this->input('document_type');
        $branchId = $this->input('branch_id');

        return [
            'branch_id' => ['required', 'uuid', 'exists:branches,id'],
            'box_id' => [
                'nullable',
                'uuid',
                Rule::exists('boxes', 'id')->where(function ($query) use ($branchId) {
                    if ($branchId) {
                        $query->where('branch_id', $branchId);
                    }
                }),
            ],
            'document_type' => ['required', Rule::enum(SalesDocumentType::class)],
            'series' => [
                'required',
                'string',
                'size:4',
                Rule::unique('document_series', 'series')
                    ->where(function ($query) use ($documentType) {
                        if ($documentType) {
                            $query->where('document_type', $documentType);
                        }
                        $query->whereNull('deleted_at');
                    })
                    ->ignore($seriesId),
            ],
            'last_number' => ['nullable', 'integer', 'min:0'],
            'is_electronic' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $typeStr = (string) $this->input('document_type');
            $type = SalesDocumentType::tryFrom($typeStr);
            $series = trim((string) $this->input('series'));

            if ($type !== null && $series !== '') {
                if (! preg_match($type->seriesRegex(), $series)) {
                    $message = match ($type) {
                        SalesDocumentType::Invoice => 'La serie de factura debe empezar con F y tener 4 caracteres alfanuméricos (ej. F001).',
                        SalesDocumentType::Receipt => 'La serie de boleta debe empezar con B y tener 4 caracteres alfanuméricos (ej. B001).',
                        SalesDocumentType::CreditNote => 'La serie de nota de crédito debe empezar con F o B y tener 4 caracteres alfanuméricos (ej. FC01, BC01).',
                        SalesDocumentType::SaleNote => 'La serie de nota de venta debe tener 4 caracteres alfanuméricos (ej. NV01).',
                    };
                    $v->errors()->add('series', $message);
                }
            }
        });
    }

    private function currentSeries(): ?DocumentSeries
    {
        $series = $this->route('document_series') ?? $this->route('id');

        if ($series instanceof DocumentSeries) {
            return $series;
        }

        return is_string($series) && Str::isUuid($series) ? DocumentSeries::find($series) : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'series.size' => 'La serie debe tener exactamente 4 caracteres.',
            'series.unique' => 'Ya existe una serie con este código para el tipo de comprobante seleccionado.',
            'box_id.exists' => 'La caja seleccionada no pertenece a la sede indicada.',
        ];
    }
}
