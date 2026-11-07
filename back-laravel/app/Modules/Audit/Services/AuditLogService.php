<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        $query = Activity::query()->with('causer');

        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $like = '%'.addcslashes($term, '%_\\').'%';

            $driver = $query->getConnection()->getDriverName();
            $operator = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function (Builder $q) use ($like, $operator) {
                $q->where('description', $operator, $like)
                    ->orWhere('log_name', $operator, $like)
                    ->orWhere('event', $operator, $like);
            });
        }

        if (! empty($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['causer_id'])) {
            $query->where('causer_id', $filters['causer_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['session_id'])) {
            $query->where('session_id', $filters['session_id']);
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Tipos de entidad presentes en el historial, para el filtro del listado.
     *
     * @return list<array{value: string, title: string}>
     */
    public function subjectTypes(): array
    {
        return array_values(
            Activity::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->pluck('subject_type')
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'title' => str_contains($type, '\\') ? class_basename($type) : Str::studly($type),
                ])
                ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
                ->all()
        );
    }

    public function findById(string $id): ?Activity
    {
        $activity = Activity::query()->with('causer')->find($id);

        return $activity instanceof Activity ? $activity : null;
    }
}
