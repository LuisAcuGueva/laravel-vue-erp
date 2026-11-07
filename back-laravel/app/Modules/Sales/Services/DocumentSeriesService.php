<?php

declare(strict_types=1);

namespace App\Modules\Sales\Services;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\DocumentSeries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class DocumentSeriesService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<DocumentSeries>
     */
    public function query(array $filters = []): Builder
    {
        $query = DocumentSeries::query()->with(['branch', 'box']);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['box_id'])) {
            $query->where('box_id', $filters['box_id']);
        }

        if (! empty($filters['document_type'])) {
            $type = $filters['document_type'] instanceof SalesDocumentType
                ? $filters['document_type']->value
                : (string) $filters['document_type'];
            $query->where('document_type', $type);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $term = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where('series', 'like', $term);
        }

        return $query->orderBy('document_type')->orderBy('series');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, DocumentSeries>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage);
    }

    /**
     * Devuelve las series activas de una sede aplicables a la caja (las de la caja primero y luego las sin caja).
     *
     * @return Collection<int, DocumentSeries>
     */
    public function available(Branch|string $branch, ?string $boxId = null, ?string $documentType = null): Collection
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        $query = DocumentSeries::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true);

        if ($documentType !== null && $documentType !== '') {
            $query->where('document_type', $documentType);
        }

        if ($boxId !== null && $boxId !== '') {
            $query->where(function (Builder $q) use ($boxId) {
                $q->whereNull('box_id')->orWhere('box_id', $boxId);
            })->orderByRaw('CASE WHEN box_id IS NOT NULL THEN 0 ELSE 1 END, series ASC');
        } else {
            $query->orderBy('series');
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DocumentSeries
    {
        $data['series'] = strtoupper(trim((string) $data['series']));

        return DocumentSeries::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DocumentSeries $series, array $data): DocumentSeries
    {
        if (array_key_exists('series', $data)) {
            $data['series'] = strtoupper(trim((string) $data['series']));
        }

        if (array_key_exists('last_number', $data) && (int) $data['last_number'] !== $series->last_number) {
            if ($series->sales()->exists()) {
                throw ValidationException::withMessages([
                    'last_number' => 'El correlativo inicial no se puede modificar porque la serie ya tiene comprobantes emitidos.',
                ]);
            }
        }

        $series->update($data);

        return $series->fresh() ?? $series;
    }

    public function delete(DocumentSeries $series): void
    {
        if ($series->sales()->exists()) {
            throw ValidationException::withMessages([
                'series' => 'No se puede eliminar una serie con comprobantes emitidos.',
            ]);
        }

        $series->delete();
    }

    /**
     * Obtiene el siguiente correlativo y bloquea la fila dentro de una transacción.
     *
     * @return array{number: int, full: string}
     */
    public function next(DocumentSeries $series): array
    {
        /** @var DocumentSeries $locked */
        $locked = DocumentSeries::whereKey($series->id)
            ->lockForUpdate()
            ->firstOrFail();

        $nextNumber = $locked->last_number + 1;
        $locked->update(['last_number' => $nextNumber]);

        return [
            'number' => $nextNumber,
            'full' => $locked->format($nextNumber),
        ];
    }
}
