<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Data\CashMovementData;
use App\Modules\Finance\Enums\CashSessionStatus;
use App\Modules\Finance\Enums\CashTransferStatus;
use App\Modules\Finance\Enums\MoneyDirection;
use App\Modules\Finance\Enums\SystemConcept;
use App\Modules\Finance\Exceptions\CashSessionException;
use App\Modules\Finance\Exceptions\CashTransferException;
use App\Modules\Finance\Exceptions\InsufficientCashException;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\CashTransfer;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Users\Models\User;
use App\Support\Branch\BranchAccessGuard;
use App\Support\Numbering\SequentialCodeGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CashTransferService
{
    public function __construct(
        private readonly CashLedgerService $ledgerService,
        private readonly CashSessionSummaryService $summaryService,
        private readonly BoxAccessService $boxAccessService,
        private readonly BranchAccessGuard $branchAccessGuard,
    ) {}

    /**
     * @param  array{source_session_id: string, target_box_id: string, amount: numeric, reason: string}  $data
     */
    public function create(array $data, User $user, Branch $activeBranch): CashTransfer
    {
        return DB::transaction(function () use ($data, $user, $activeBranch) {
            /** @var CashSession|null $sourceSession */
            $sourceSession = CashSession::where('id', $data['source_session_id'])->lockForUpdate()->first();

            if ($sourceSession === null) {
                throw new CashSessionException('Sesión de caja de origen no encontrada.');
            }

            if ($sourceSession->status !== CashSessionStatus::Open) {
                throw new CashSessionException('La sesión de caja de origen no está abierta.');
            }

            if ($sourceSession->branch_id !== $activeBranch->id) {
                throw new CashSessionException('La sesión de origen no pertenece a la sede activa.');
            }

            $sourceBox = $sourceSession->box;
            if ($sourceBox === null) {
                throw new CashSessionException('Caja de origen no encontrada.');
            }

            if (! $this->boxAccessService->canOperate($user, $sourceBox)) {
                throw new AccessDeniedHttpException('No tiene permisos para operar la caja de origen.');
            }

            /** @var Box|null $targetBox */
            $targetBox = Box::find($data['target_box_id']);
            if ($targetBox === null || ! $targetBox->is_active) {
                throw new CashTransferException('La caja de destino no existe o no está activa.');
            }

            if ($targetBox->id === $sourceBox->id) {
                throw new CashTransferException('La caja de destino debe ser diferente a la caja de origen.');
            }

            $this->branchAccessGuard->assertAccessible($user, $targetBox->branch_id);

            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw new CashTransferException('El monto debe ser mayor a cero.');
            }

            $availableCash = (float) $this->summaryService->availableCash($sourceSession);
            if ($amount > $availableCash) {
                throw new InsufficientCashException('Efectivo insuficiente en caja.');
            }

            $number = SequentialCodeGenerator::next('cash_transfer', 'TC', 6);

            return CashTransfer::create([
                'number' => $number,
                'status' => CashTransferStatus::Pending,
                'source_session_id' => $sourceSession->id,
                'source_box_id' => $sourceBox->id,
                'source_branch_id' => $sourceSession->branch_id,
                'target_box_id' => $targetBox->id,
                'target_branch_id' => $targetBox->branch_id,
                'target_session_id' => null,
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'created_by' => $user->id,
            ]);
        });
    }

    public function approve(CashTransfer $transfer, User $user): CashTransfer
    {
        return DB::transaction(function () use ($transfer, $user) {
            /** @var CashTransfer $lockedTransfer */
            $lockedTransfer = CashTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== CashTransferStatus::Pending) {
                throw new CashTransferException('La transferencia no está pendiente de aprobación.');
            }

            if ($lockedTransfer->created_by === $user->id) {
                throw new CashTransferException('No puedes aprobar una transferencia creada por ti mismo.');
            }

            $targetBox = $lockedTransfer->targetBox;
            $targetSession = $this->ledgerService->currentSession($targetBox);

            if ($targetSession === null) {
                throw new CashTransferException('La caja de destino no tiene sesión abierta.');
            }

            // Bloqueo determinista de sesiones ordenadas por id para evitar deadlocks
            $sessionIds = collect([$lockedTransfer->source_session_id, $targetSession->id])->sort()->values()->all();
            $lockedSessions = CashSession::whereIn('id', $sessionIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            /** @var CashSession|null $lockedSource */
            $lockedSource = $lockedSessions->get($lockedTransfer->source_session_id);
            /** @var CashSession|null $lockedTarget */
            $lockedTarget = $lockedSessions->get($targetSession->id);

            if ($lockedSource === null || $lockedSource->status !== CashSessionStatus::Open) {
                throw new CashTransferException('La sesión de origen ya no está abierta.');
            }

            if ($lockedTarget === null || $lockedTarget->status !== CashSessionStatus::Open) {
                throw new CashTransferException('La caja de destino no tiene sesión abierta.');
            }

            // Validar efectivo disponible en origen excluyendo esta transferencia pendiente
            $available = (float) $this->summaryService->availableCash($lockedSource, $lockedTransfer->id);
            if ((float) $lockedTransfer->amount > $available) {
                throw new InsufficientCashException('Efectivo insuficiente en caja.');
            }

            // Registrar salida de efectivo en sesión de origen
            $this->ledgerService->register(new CashMovementData(
                sessionId: $lockedSource->id,
                direction: MoneyDirection::Out,
                amount: (string) $lockedTransfer->amount,
                conceptCode: SystemConcept::CashTransferOut->value,
                paymentMethodCode: 'CASH',
                description: "Transferencia a {$targetBox->name} ({$lockedTransfer->number})",
                source: $lockedTransfer,
                sourceNumber: $lockedTransfer->number,
                createdBy: $user->id,
                isManual: false,
            ));

            // Registrar entrada de efectivo en sesión de destino
            $this->ledgerService->register(new CashMovementData(
                sessionId: $lockedTarget->id,
                direction: MoneyDirection::In,
                amount: (string) $lockedTransfer->amount,
                conceptCode: SystemConcept::CashTransferIn->value,
                paymentMethodCode: 'CASH',
                description: "Transferencia desde {$lockedTransfer->sourceBox->name} ({$lockedTransfer->number})",
                source: $lockedTransfer,
                sourceNumber: $lockedTransfer->number,
                createdBy: $user->id,
                isManual: false,
            ));

            $lockedTransfer->update([
                'status' => CashTransferStatus::Approved,
                'target_session_id' => $lockedTarget->id,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return $lockedTransfer->fresh(['sourceBox', 'targetBox', 'sourceBranch', 'targetBranch', 'creator', 'approver']) ?? $lockedTransfer;
        });
    }

    public function reject(CashTransfer $transfer, string $reason, User $user): CashTransfer
    {
        return DB::transaction(function () use ($transfer, $reason, $user) {
            /** @var CashTransfer $lockedTransfer */
            $lockedTransfer = CashTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== CashTransferStatus::Pending) {
                throw new CashTransferException('Solo se pueden rechazar transferencias pendientes.');
            }

            $lockedTransfer->update([
                'status' => CashTransferStatus::Rejected,
                'rejected_by' => $user->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $lockedTransfer->fresh(['sourceBox', 'targetBox', 'sourceBranch', 'targetBranch', 'creator', 'rejecter']) ?? $lockedTransfer;
        });
    }

    public function cancel(CashTransfer $transfer, string $reason, User $user): CashTransfer
    {
        return DB::transaction(function () use ($transfer, $reason, $user) {
            /** @var CashTransfer $lockedTransfer */
            $lockedTransfer = CashTransfer::where('id', $transfer->id)->lockForUpdate()->firstOrFail();

            if ($lockedTransfer->status !== CashTransferStatus::Pending) {
                throw new CashTransferException('Solo se pueden cancelar transferencias pendientes.');
            }

            if ($lockedTransfer->created_by !== $user->id) {
                throw new AccessDeniedHttpException('Solo el creador puede cancelar la transferencia.');
            }

            $lockedTransfer->update([
                'status' => CashTransferStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            return $lockedTransfer->fresh(['sourceBox', 'targetBox', 'sourceBranch', 'targetBranch', 'creator', 'canceller']) ?? $lockedTransfer;
        });
    }

    public function findById(string $id): ?CashTransfer
    {
        return CashTransfer::with([
            'sourceSession',
            'targetSession',
            'sourceBox',
            'targetBox',
            'sourceBranch',
            'targetBranch',
            'creator',
            'approver',
            'rejecter',
            'canceller',
            'cashMovements',
        ])->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<CashTransfer>
     */
    public function query(Branch $activeBranch, User $user, array $filters = []): Builder
    {
        $query = CashTransfer::query()->with([
            'sourceBox',
            'targetBox',
            'sourceBranch',
            'targetBranch',
            'creator',
            'approver',
            'rejecter',
            'canceller',
        ]);

        $direction = $filters['direction'] ?? null;

        if ($direction === 'sent') {
            $query->where('source_branch_id', $activeBranch->id);
        } elseif ($direction === 'received') {
            $query->where('target_branch_id', $activeBranch->id);
        } else {
            $query->where(function (Builder $q) use ($activeBranch) {
                $q->where('source_branch_id', $activeBranch->id)
                    ->orWhere('target_branch_id', $activeBranch->id);
            });
        }

        // Si el usuario no tiene permiso amplio de ver todas las transferencias de la sede
        if (! $user->can('cash_transfers.view') && $user->can('cash_transfers.create')) {
            $accessibleBoxes = $this->boxAccessService->availableBoxes($user, $activeBranch)->pluck('id')->all();
            $query->where(function (Builder $q) use ($accessibleBoxes) {
                $q->whereIn('source_box_id', $accessibleBoxes)
                    ->orWhereIn('target_box_id', $accessibleBoxes);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['box_id'])) {
            $boxId = $filters['box_id'];
            $query->where(function (Builder $q) use ($boxId) {
                $q->where('source_box_id', $boxId)
                    ->orWhere('target_box_id', $boxId);
            });
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('number', 'like', $search)
                    ->orWhere('reason', 'like', $search);
            });
        }

        return $query->orderByDesc('created_at');
    }

    public function pendingCount(Branch $activeBranch, User $user): int
    {
        return $this->query($activeBranch, $user, ['status' => CashTransferStatus::Pending->value])->count();
    }
}
