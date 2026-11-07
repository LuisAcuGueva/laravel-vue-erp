<?php

declare(strict_types=1);

namespace App\Modules\Sales\Jobs;

use App\Modules\Sales\EInvoicing\EInvoicingManager;
use App\Modules\Sales\Enums\EInvoiceStatus;
use App\Modules\Sales\Models\CreditNote;
use App\Modules\Sales\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendElectronicDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $saleId,
        public readonly string $documentType = 'sale',
    ) {}

    public function uniqueId(): string
    {
        return "{$this->documentType}:{$this->saleId}";
    }

    public function handle(EInvoicingManager $manager): void
    {
        /** @var Sale|CreditNote|null $document */
        $document = $this->documentType === 'credit_note'
            ? CreditNote::find($this->saleId)
            : Sale::find($this->saleId);

        if ($document === null || $document->status->value === 'cancelled') {
            return;
        }

        $document->increment('einvoice_attempts');

        // El resultado lo decide el proveedor de facturación electrónica, no un usuario:
        // el log de actividad resultante se marca como acción de sistema (sin causer humano)
        // en vez de dejar causer_type/causer_id en NULL de forma indistinguible de un bug.
        $document->loggedAsSystemAction = true;

        try {
            $driver = $manager->driver();
            $result = $driver->send($document);

            $document->update([
                'einvoice_status' => $result->status,
                'einvoice_response' => ! empty($result->raw)
                    ? $result->raw
                    : [
                        'code' => $result->code,
                        'message' => $result->message,
                        'hash' => $result->hash,
                    ],
                'einvoice_sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $document->update([
                'einvoice_status' => EInvoiceStatus::Error,
                'einvoice_response' => [
                    'code' => 'EXCEPTION',
                    'message' => $e->getMessage(),
                ],
                'einvoice_sent_at' => now(),
            ]);
        }
    }
}
