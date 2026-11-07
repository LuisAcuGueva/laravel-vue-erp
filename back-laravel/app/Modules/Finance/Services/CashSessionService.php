<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Configuration\Services\SystemParameterService;
use App\Modules\Finance\Enums\CashReviewStatus;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Exceptions\CashSessionException;
use App\Modules\Finance\Exceptions\FinanceRuleException;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashCountDetail;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Users\Models\User;
use App\Support\Numbering\SequentialCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CashSessionService
{
    public function __construct(
        private readonly CashLedgerService $ledgerService,
        private readonly CashSessionSummaryService $summaryService,
        private readonly BoxAccessService $boxAccessService,
        private readonly SystemParameterService $parameterService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CashSession>
     */
    public function query(array $filters = []): Builder
    {
        $query = CashSession::query()->with(['box', 'opener', 'closer', 'reviewer']);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (string) $filters['branch_id']);
        }

        if (array_key_exists('branch_ids', $filters) && is_array($filters['branch_ids'])) {
            $query->whereIn('branch_id', $filters['branch_ids']);
        }

        if (! empty($filters['box_id'])) {
            $query->where('box_id', (string) $filters['box_id']);
        }

        if (array_key_exists('box_ids', $filters) && is_array($filters['box_ids'])) {
            $query->whereIn('box_id', $filters['box_ids']);
        }

        if (! empty($filters['status'])) {
            $statusVal = $filters['status'] instanceof CashSessionStatus
                ? $filters['status']->value
                : (string) $filters['status'];
            $query->where('status', $statusVal);
        }

        if (! empty($filters['review_status'])) {
            $reviewVal = $filters['review_status'] instanceof CashReviewStatus
                ? $filters['review_status']->value
                : (string) $filters['review_status'];
            $query->where('review_status', $reviewVal);
        }

        if (! empty($filters['opened_by'])) {
            $query->where('opened_by', (string) $filters['opened_by']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('opened_at', '>=', $filters['date_from'].' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $query->where('opened_at', '<=', $filters['date_to'].' 23:59:59');
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $like = '%'.addcslashes($search, '%_\\').'%';
            $driver = $query->getConnection()->getDriverName();
            $op = $driver === 'pgsql' ? 'ilike' : 'like';

            $query->where(function (Builder $q) use ($like, $op) {
                $q->where('number', $op, $like)
                    ->orWhereHas('box', function (Builder $boxQ) use ($like, $op) {
                        $boxQ->where('name', $op, $like)
                            ->orWhere('code', $op, $like);
                    });
            });
        }

        return $query->orderByDesc('opened_at');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CashSession>
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? 1);

        return $this->query($filters)->paginate(perPage: $perPage, page: $page);
    }

    public function findById(string $id): ?CashSession
    {
        return CashSession::query()
            ->with(['box', 'opener', 'closer', 'reviewer', 'countDetails'])
            ->find($id);
    }

    public function open(Box $box, float $openingAmount, ?string $notes, User $user): CashSession
    {
        if (! $box->is_active) {
            throw new CashSessionException('La caja no está activa.');
        }

        if (! $this->boxAccessService->canOperate($user, $box)) {
            throw new CashSessionException('No tiene permisos para operar esta caja.', 403);
        }

        return DB::transaction(function () use ($box, $openingAmount, $notes, $user): CashSession {
            /** @var Box $lockedBox */
            $lockedBox = Box::where('id', $box->id)->lockForUpdate()->firstOrFail();

            /** @var CashSession|null $existingOpen */
            $existingOpen = $lockedBox->openSession()->first();
            if ($existingOpen !== null) {
                throw new CashSessionException("La caja ya tiene una sesión abierta ({$existingOpen->number}).", 409);
            }

            $number = SequentialCodeGenerator::next('cash_session', 'CJ', 6);

            /** @var CashSession $session */
            $session = CashSession::create([
                'number' => $number,
                'box_id' => $lockedBox->id,
                'branch_id' => $lockedBox->branch_id,
                'status' => CashSessionStatus::Open->value,
                'opened_by' => $user->id,
                'opened_at' => now(),
                'opening_amount' => $openingAmount,
                'opening_notes' => $notes,
            ]);

            return $session->load(['box', 'opener']);
        });
    }

    /**
     * @param  list<array{denomination: numeric, quantity: int}>|null  $count
     */
    public function close(
        CashSession $session,
        ?array $count,
        ?float $countedCash,
        ?string $notes,
        User $user
    ): CashSession {
        return DB::transaction(function () use ($session, $count, $countedCash, $notes, $user): CashSession {
            /** @var CashSession $lockedSession */
            $lockedSession = CashSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            $statusVal = $lockedSession->status instanceof CashSessionStatus
                ? $lockedSession->status
                : CashSessionStatus::tryFrom((string) $lockedSession->status);

            if ($statusVal !== CashSessionStatus::Open) {
                throw new CashSessionException('La sesión no está abierta.');
            }

            $totalCounted = 0.0;
            $countDetailsToInsert = [];

            if ($count !== null && count($count) > 0) {
                /** @var list<int|float|string> $validDenominations */
                $validDenominations = (array) $this->parameterService->get(
                    'finance.cash_denominations',
                    [200, 100, 50, 20, 10, 5, 2, 1, 0.5, 0.2, 0.1]
                );
                $validFloats = array_map(fn ($d): float => (float) $d, $validDenominations);

                foreach ($count as $item) {
                    $denomination = (float) $item['denomination'];
                    $quantity = (int) $item['quantity'];

                    // Verificación de denominación contra catálogo
                    $isValid = false;
                    foreach ($validFloats as $v) {
                        if (abs($v - $denomination) < 0.001) {
                            $isValid = true;
                            break;
                        }
                    }

                    if (! $isValid) {
                        throw new FinanceRuleException("La denominación {$item['denomination']} no es válida.", 'count');
                    }

                    $subtotal = round($denomination * $quantity, 2);
                    $totalCounted += $subtotal;

                    $countDetailsToInsert[] = [
                        'cash_session_id' => $lockedSession->id,
                        'denomination' => $denomination,
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                    ];
                }
            } elseif ($countedCash !== null) {
                $totalCounted = $countedCash;
            } else {
                throw new FinanceRuleException('Debe proporcionar el detalle del conteo o el total de efectivo contado.', 'counted_cash');
            }

            $expected = (float) $this->summaryService->expectedCash($lockedSession);
            $difference = round($totalCounted - $expected, 2);

            $tolerance = $this->parameterService->decimal('finance.cash_difference_tolerance', 5.0);
            $isWithinTolerance = abs($difference) <= $tolerance;

            if ($isWithinTolerance) {
                $reviewStatus = CashReviewStatus::Approved;
            } else {
                $reviewStatus = CashReviewStatus::Pending;
                if (blank($notes)) {
                    throw new FinanceRuleException('Se requieren notas de cierre cuando existe diferencia fuera de tolerancia.', 'closing_notes');
                }
            }

            // Guardar detalles del conteo
            foreach ($countDetailsToInsert as $detailData) {
                CashCountDetail::create($detailData);
            }

            $lockedSession->update([
                'status' => CashSessionStatus::Closed->value,
                'closed_by' => $user->id,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $totalCounted,
                'difference' => $difference,
                'closing_notes' => $notes,
                'review_status' => $reviewStatus->value,
            ]);

            return $lockedSession->fresh(['box', 'countDetails', 'opener', 'closer']) ?? $lockedSession;
        });
    }

    public function review(
        CashSession $session,
        string $decision,
        ?string $notes,
        User $user
    ): CashSession {
        return DB::transaction(function () use ($session, $decision, $notes, $user): CashSession {
            /** @var CashSession $lockedSession */
            $lockedSession = CashSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            $statusVal = $lockedSession->status instanceof CashSessionStatus
                ? $lockedSession->status
                : CashSessionStatus::tryFrom((string) $lockedSession->status);

            if ($statusVal !== CashSessionStatus::Closed) {
                throw new CashSessionException('Solo se pueden revisar sesiones cerradas.');
            }

            $reviewVal = $lockedSession->review_status instanceof CashReviewStatus
                ? $lockedSession->review_status
                : CashReviewStatus::tryFrom((string) $lockedSession->review_status);

            if ($reviewVal !== CashReviewStatus::Pending) {
                throw new CashSessionException('La sesión no se encuentra pendiente de revisión.');
            }

            if ($lockedSession->closed_by === $user->id) {
                throw new FinanceRuleException('El usuario que cerró la sesión no puede revisarla.', 'reviewer');
            }

            if (! in_array($decision, ['approved', 'rejected'], true)) {
                throw new FinanceRuleException('Decisión de revisión inválida.', 'decision');
            }

            if ($decision === 'rejected' && blank($notes)) {
                throw new FinanceRuleException('Se requieren notas de revisión al rechazar.', 'notes');
            }

            $finalStatus = $decision === 'approved'
                ? CashReviewStatus::Approved
                : CashReviewStatus::Rejected;

            $lockedSession->update([
                'review_status' => $finalStatus->value,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            // Si es aprobada y hay diferencia, registrar movimiento informativo
            if ($decision === 'approved' && $lockedSession->difference !== null) {
                $diff = (float) $lockedSession->difference;
                if ($diff > 0.001) {
                    $this->ledgerService->registerInformative(
                        $lockedSession,
                        SystemConcept::CashOverage,
                        number_format(abs($diff), 2, '.', ''),
                        "Ajuste por sobrante de caja al cierre ({$lockedSession->number})"
                    );
                } elseif ($diff < -0.001) {
                    $this->ledgerService->registerInformative(
                        $lockedSession,
                        SystemConcept::CashShortage,
                        number_format(abs($diff), 2, '.', ''),
                        "Ajuste por faltante de caja al cierre ({$lockedSession->number})"
                    );
                }
            }

            return $lockedSession->fresh(['box', 'countDetails', 'opener', 'closer', 'reviewer']) ?? $lockedSession;
        });
    }
}
