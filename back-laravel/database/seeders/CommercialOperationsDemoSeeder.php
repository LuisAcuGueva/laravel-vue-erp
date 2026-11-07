<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\Box;
use App\Modules\Finance\Models\CashSession;
use App\Modules\Finance\Models\Currency;
use App\Modules\Finance\Models\PaymentMethod;
use App\Modules\Finance\Services\BankOperationService;
use App\Modules\Finance\Services\CashSessionService;
use App\Modules\Finance\Services\CashSessionSummaryService;
use App\Modules\Finance\Services\CashTransferService;
use App\Modules\Inventory\Enums\PriceType;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Inventory\Models\ItemPresentation;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Sales\Enums\CreditNoteReason;
use App\Modules\Sales\Enums\SalesDocumentType;
use App\Modules\Sales\Models\DocumentSeries;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\CollectionService;
use App\Modules\Sales\Services\CreditNoteIssuanceService;
use App\Modules\Sales\Services\SaleIssuanceService;
use App\Modules\Sales\Services\SalePricingService;
use App\Modules\Users\Models\User;
use App\Support\Pricing\DocumentTotalsCalculator;
use App\Support\Pricing\LineInput;
use Carbon\Carbon;
use Database\Seeders\Concerns\TravelsInTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Narrativa comercial y de caja demo, construida día por día con fechas reales
 * (TravelsInTime) para que ventas, cobranzas, nota de crédito, turnos de caja,
 * transferencias y operaciones bancarias queden todos en la línea de tiempo
 * correcta: turnos de caja de 8am a 6pm en días distintos, movimientos dentro
 * de la ventana de su propio turno, y ningún documento del mismo tipo
 * repitiendo fecha/hora sin motivo de negocio.
 *
 * Reemplaza a los antiguos SalesTransactionsDemoSeeder, CollectionsDemoSeeder,
 * CreditNoteDemoSeeder, CashSessionsDemoSeeder, CashSessionsCloseDemoSeeder,
 * CashTransferDemoSeeder y BankOperationsDemoSeeder, que trataban cada dominio
 * por separado y no podían entrelazar los turnos de caja con las ventas/
 * transferencias/operaciones que ocurren dentro de ellos.
 *
 * Es idempotente (se omite si ya existen ventas demo con la marca DEMO-SALE-).
 *
 * Depende de: QuotationsDemoSeeder, CatalogSeeder, StockDemoSeeder,
 * ClientSeeder, EmployeeSeeder, SalesDemoSeeder (series), FinanceDemoSeeder
 * (cajas), BankPeriodsDemoSeeder, SupplierInvoicesDemoSeeder.
 */
class CommercialOperationsDemoSeeder extends Seeder
{
    use TravelsInTime;

    public function run(): void
    {
        if (Sale::where('notes', 'like', '%DEMO-SALE-%')->exists()) {
            return;
        }

        $branch = Branch::where('is_main', true)->first() ?? Branch::first();
        $seller = Employee::where('document_number', '70123456')->first();
        $pen = Currency::base() ?? Currency::where('code', 'PEN')->first();
        $user = User::where('email', 'user@example.com')->first();
        $manager = User::where('email', 'manager@example.com')->first();
        $admin = User::where('email', 'admin@example.com')->first();
        $caja01 = Box::where('code', 'CAJA01')->first();
        $caja02 = Box::where('code', 'CAJA02')->first();

        if ($branch === null || $pen === null || $user === null || $manager === null
            || $admin === null || $caja01 === null || $caja02 === null) {
            return;
        }

        $today = now();
        $catalog = CatalogItem::with('defaultPresentation')->get()->keyBy('code');

        // =========================================================================
        // T-9: Venta C — Factura F001 al crédito a 2 cuotas (San Jorge). Independiente
        // de cualquier caja (no cobra al contado), así que no necesita sesión abierta.
        // =========================================================================
        $this->at($today->copy()->subDays(9)->setTime(11, 0), function () use ($branch, $seller, $pen, $admin, $catalog) {
            $sanJorge = Client::where('document_number', '20123456789')->first();
            $invoiceSeries = DocumentSeries::where('document_type', SalesDocumentType::Invoice)->where('series', 'F001')->first();
            if ($sanJorge === null || $invoiceSeries === null) {
                return;
            }

            [$itemsC] = $this->buildLines($catalog, [
                ['code' => 'ITM-000001', 'qty' => 1.0],
                ['code' => 'ITM-000005', 'qty' => 2.0],
            ], $pen);

            if ($itemsC === null) {
                return;
            }

            app(SaleIssuanceService::class)->issue([
                'document_series_id' => $invoiceSeries->id,
                'warehouse_id' => $this->mainWarehouseId($branch),
                'client_id' => $sanJorge->id,
                'currency_id' => $pen->id,
                'seller_id' => $seller?->id,
                'prices_include_tax' => true,
                'items' => $itemsC,
                'payment_condition' => 'credit',
                'payments' => [],
                'installments_count' => 2,
                'installment_interval_days' => 15,
                'notes' => 'Venta demo al crédito a 2 cuotas (DEMO-SALE-CREDIT).',
            ], $admin, $branch);
        });

        // =========================================================================
        // T-7: Venta B — Boleta B001 contado con tarjeta (María Flores). Liquida en
        // banco (Interbank Soles), no en caja: tampoco necesita sesión abierta.
        // =========================================================================
        $this->at($today->copy()->subDays(7)->setTime(16, 0), function () use ($branch, $seller, $pen, $user, $catalog) {
            $mariaFlores = Client::where('document_number', '42345678')->first();
            $receiptSeries = DocumentSeries::where('document_type', SalesDocumentType::Receipt)->where('series', 'B001')->first();
            if ($mariaFlores === null || $receiptSeries === null) {
                return;
            }

            [$itemsB, $totalB] = $this->buildLines($catalog, [
                ['code' => 'ITM-000002', 'qty' => 1.0],
                ['code' => 'ITM-000003', 'qty' => 2.0],
            ], $pen);

            if ($itemsB === null) {
                return;
            }

            app(SaleIssuanceService::class)->issue([
                'document_series_id' => $receiptSeries->id,
                'warehouse_id' => $this->mainWarehouseId($branch),
                'client_id' => $mariaFlores->id,
                'currency_id' => $pen->id,
                'seller_id' => $seller?->id,
                'prices_include_tax' => true,
                'items' => $itemsB,
                'payment_condition' => 'cash',
                'payments' => [[
                    'payment_method_id' => $this->paymentMethodId('CARD_POS'),
                    'amount' => $totalB,
                    'reference' => 'VISA-884321',
                ]],
                'notes' => 'Venta demo contado con tarjeta (DEMO-SALE-CARD).',
            ], $user, $branch);
        });

        // =========================================================================
        // T-5: CAJA02 — turno histórico 8am-6pm, sin novedad (cierre exacto).
        // =========================================================================
        $caja02ShiftA = $this->at($today->copy()->subDays(5)->setTime(8, 0), fn () => app(CashSessionService::class)
            ->open($caja02, 300.00, 'Apertura de caja Miraflores.', $manager));

        $this->closeExact($caja02ShiftA, $today->copy()->subDays(5)->setTime(18, 0), $manager);

        // =========================================================================
        // T-4: CAJA01 — turno 8am-6pm. Venta A (desde la cotización aceptada),
        // una transferencia de caja rechazada y un depósito a BCP Soles. Cierra
        // con faltante fuera de tolerancia (revisión al día siguiente).
        // =========================================================================
        $caja01Shift2 = $this->at($today->copy()->subDays(4)->setTime(8, 0), fn () => app(CashSessionService::class)
            ->open($caja01, 500.00, 'Apertura de caja principal.', $user));

        $this->at($today->copy()->subDays(4)->setTime(10, 30), function () use ($branch, $seller, $pen, $user, $catalog, $caja01Shift2) {
            $juanPerez = Client::where('document_number', '41234567')->first();
            $quotation = Quotation::where('notes', 'like', '%DEMO-QUOTE-CONVERTED%')->first();
            $receiptSeries = DocumentSeries::where('document_type', SalesDocumentType::Receipt)->where('series', 'B001')->first();
            if ($juanPerez === null || $receiptSeries === null) {
                return;
            }

            [$itemsA, $totalA] = $this->buildLines($catalog, [
                ['code' => 'ITM-000001', 'qty' => 1.0],
                ['code' => 'ITM-000004', 'qty' => 1.0],
            ], $pen);

            if ($itemsA === null) {
                return;
            }

            $receivedA = (string) (ceil(((float) $totalA) / 50) * 50);

            app(SaleIssuanceService::class)->issue([
                'document_series_id' => $receiptSeries->id,
                'warehouse_id' => $this->mainWarehouseId($branch),
                'cash_session_id' => $caja01Shift2->id,
                'client_id' => $juanPerez->id,
                'currency_id' => $pen->id,
                'seller_id' => $seller?->id,
                'quotation_id' => $quotation?->id,
                'prices_include_tax' => true,
                'items' => $itemsA,
                'payment_condition' => 'cash',
                'payments' => [[
                    'payment_method_id' => $this->paymentMethodId('CASH'),
                    'amount' => $totalA,
                    'received_amount' => $receivedA,
                ]],
                'notes' => 'Venta demo contado en efectivo (DEMO-SALE-CASH).',
            ], $user, $branch);
        });

        $transferRejected = $this->at($today->copy()->subDays(4)->setTime(11, 0), fn () => app(CashTransferService::class)->create([
            'source_session_id' => $caja01Shift2->id,
            'target_box_id' => $caja02->id,
            'amount' => '150.00',
            'reason' => 'Fondo adicional para cambio en Miraflores (DEMO-TRANSFER-REJECTED).',
        ], $manager, $branch));

        $this->at($today->copy()->subDays(4)->setTime(14, 0), fn () => app(CashTransferService::class)
            ->reject($transferRejected, 'Monto no autorizado para esta fecha, se solicita reprogramar.', $admin));

        $this->at($today->copy()->subDays(4)->setTime(15, 0), fn () => app(BankOperationService::class)->create([
            'type' => 'deposit',
            'cash_session_id' => $caja01Shift2->id,
            'bank_account_id' => $this->bankAccountId('BCP Soles'),
            'amount' => '300.00',
            'operation_date' => now()->toDateString(),
            'operation_number' => 'DEMO-DEP-001',
            'notes' => 'Depósito de excedente de caja principal.',
        ], $user));

        $this->closeWithDifference(
            $caja01Shift2,
            $today->copy()->subDays(4)->setTime(18, 0),
            -20.00,
            'Faltante detectado al arqueo, pendiente de revisión (DEMO-CLOSE-SHORTAGE).',
            $user
        );

        // Revisión del cierre anterior, al día siguiente por la mañana.
        $this->at($today->copy()->subDays(3)->setTime(9, 0), fn () => app(CashSessionService::class)->review(
            $caja01Shift2->fresh() ?? $caja01Shift2,
            'approved',
            'Faltante investigado: diferencia por redondeo en vueltos, se acepta.',
            $admin
        ));

        $this->at($today->copy()->subDays(3)->setTime(14, 0), fn () => app(BankOperationService::class)->create([
            'type' => 'transfer',
            'bank_account_id' => $this->bankAccountId('BCP Soles'),
            'target_bank_account_id' => $this->bankAccountId('BBVA Soles'),
            'amount' => '5000.00',
            'operation_date' => now()->toDateString(),
            'operation_number' => 'DEMO-TRF-001',
            'notes' => 'Consolidación de fondos para pago a proveedores.',
        ], $user));

        // =========================================================================
        // T-2: CAJA01 y CAJA02 abren el mismo día. Cobro de la 1ra cuota en
        // efectivo, transferencia de caja aprobada (CAJA02 ya está abierta para
        // recibirla), retiro desde BBVA Soles luego anulado por error. CAJA01
        // cierra con sobrante fuera de tolerancia.
        // =========================================================================
        $caja01Shift3 = $this->at($today->copy()->subDays(2)->setTime(8, 0), fn () => app(CashSessionService::class)
            ->open($caja01, 500.00, 'Apertura de caja principal.', $user));

        $caja02ShiftB = $this->at($today->copy()->subDays(2)->setTime(8, 0), fn () => app(CashSessionService::class)
            ->open($caja02, 300.00, 'Apertura de caja Miraflores.', $manager));

        $this->at($today->copy()->subDays(2)->setTime(9, 30), function () use ($user, $branch, $caja01Shift3) {
            $sale = Sale::where('notes', 'like', '%DEMO-SALE-CREDIT%')
                ->with(['installments' => fn ($q) => $q->orderBy('number')])
                ->first();
            $firstInstallment = $sale?->installments->get(0);

            if ($sale === null || $firstInstallment === null || (float) $firstInstallment->balance <= 0) {
                return;
            }

            app(CollectionService::class)->create([
                'client_id' => $sale->client_id,
                'currency_id' => $sale->currency_id,
                'payment_method_id' => $this->paymentMethodId('CASH'),
                'cash_session_id' => $caja01Shift3->id,
                'amount' => (string) $firstInstallment->balance,
                'allocations' => [
                    ['installment_id' => $firstInstallment->id, 'amount' => (string) $firstInstallment->balance],
                ],
                'notes' => "Cobro de la cuota {$firstInstallment->number} de {$sale->full_number} (DEMO-COLLECTION-CASH).",
            ], $user, $branch);
        });

        $transferApproved = $this->at($today->copy()->subDays(2)->setTime(10, 0), fn () => app(CashTransferService::class)->create([
            'source_session_id' => $caja01Shift3->id,
            'target_box_id' => $caja02->id,
            'amount' => '200.00',
            'reason' => 'Reposición de fondo de caja Miraflores (DEMO-TRANSFER-APPROVED).',
        ], $manager, $branch));

        $withdrawal = $this->at($today->copy()->subDays(2)->setTime(11, 0), fn () => app(BankOperationService::class)->create([
            'type' => 'withdrawal',
            'cash_session_id' => $caja01Shift3->id,
            'bank_account_id' => $this->bankAccountId('BBVA Soles'),
            'amount' => '250.00',
            'operation_date' => now()->toDateString(),
            'operation_number' => 'DEMO-RET-001',
            'notes' => 'Retiro para fondo de caja principal.',
        ], $user));

        $this->at($today->copy()->subDays(2)->setTime(15, 0), fn () => app(CashTransferService::class)
            ->approve($transferApproved, $admin));

        $this->at($today->copy()->subDays(2)->setTime(16, 0), fn () => app(BankOperationService::class)
            ->cancel($withdrawal, 'Registrado dos veces por error, se anula.', $admin));

        $this->closeWithDifference(
            $caja01Shift3,
            $today->copy()->subDays(2)->setTime(18, 0),
            12.00,
            'Sobrante detectado al arqueo, pendiente de revisión (DEMO-CLOSE-OVERAGE).',
            $user
        );

        $this->closeExact($caja02ShiftB, $today->copy()->subDays(2)->setTime(18, 0), $manager);

        // =========================================================================
        // T-1: revisión del cierre anterior, cobro bancario de la 2da cuota y
        // nota de crédito de la Venta A, reembolsada por banco (no depende de
        // ninguna caja abierta ese día).
        // =========================================================================
        $this->at($today->copy()->subDays(1)->setTime(9, 0), fn () => app(CashSessionService::class)->review(
            $caja01Shift3->fresh() ?? $caja01Shift3,
            'approved',
            'Sobrante investigado: vueltos entregados de menos, se acepta.',
            $admin
        ));

        $this->at($today->copy()->subDays(1)->setTime(11, 0), function () use ($user, $branch) {
            $sale = Sale::where('notes', 'like', '%DEMO-SALE-CREDIT%')
                ->with(['installments' => fn ($q) => $q->orderBy('number')])
                ->first();
            $secondInstallment = $sale?->installments->get(1);

            if ($sale === null || $secondInstallment === null || (float) $secondInstallment->balance <= 0) {
                return;
            }

            $partial = min(1500.00, (float) $secondInstallment->balance);

            app(CollectionService::class)->create([
                'client_id' => $sale->client_id,
                'currency_id' => $sale->currency_id,
                'payment_method_id' => $this->paymentMethodId('TRANSFER_BCP'),
                'reference' => 'OP-442190',
                'amount' => number_format($partial, 2, '.', ''),
                'allocations' => [
                    ['installment_id' => $secondInstallment->id, 'amount' => number_format($partial, 2, '.', '')],
                ],
                'notes' => "Cobro parcial de la cuota {$secondInstallment->number} de {$sale->full_number} (DEMO-COLLECTION-BANK).",
            ], $user, $branch);
        });

        $this->at($today->copy()->subDays(1)->setTime(15, 0), function () use ($admin) {
            $sale = Sale::where('notes', 'like', '%DEMO-SALE-CASH%')->with(['items.catalogItem'])->first();
            $series = DocumentSeries::where('document_type', SalesDocumentType::CreditNote)->where('series', 'BC01')->first();

            if ($sale === null || $series === null) {
                return;
            }

            $saleItem = $sale->items->first(fn ($item) => $item->catalogItem?->code === 'ITM-000004');
            if ($saleItem === null) {
                return;
            }

            app(CreditNoteIssuanceService::class)->issue([
                'sale_id' => $sale->id,
                'document_series_id' => $series->id,
                'reason_code' => CreditNoteReason::DEVOLUCION_ITEM->value,
                'reason_description' => 'Cliente devuelve el mouse por incompatibilidad (DEMO-CREDIT-NOTE).',
                'items' => [
                    ['sale_item_id' => $saleItem->id, 'quantity' => '1.0000', 'return_to_stock' => true],
                ],
                'settlement' => [
                    'mode' => 'refund',
                    'payment_method_id' => $this->paymentMethodId('TRANSFER_BCP'),
                    'reference' => 'OP-442277',
                ],
            ], $admin);
        });

        // =========================================================================
        // Hoy: CAJA01 abre su turno actual (queda abierto). Una transferencia se
        // crea y se cancela por su propio creador; otra queda pendiente de
        // aprobación (en tránsito hacia CAJA02).
        // =========================================================================
        $caja01Shift4 = $this->at($today->copy()->setTime(8, 0), fn () => app(CashSessionService::class)
            ->open($caja01, 500.00, 'Apertura de caja principal.', $user));

        $transferCancelled = $this->at($today->copy()->setTime(9, 0), fn () => app(CashTransferService::class)->create([
            'source_session_id' => $caja01Shift4->id,
            'target_box_id' => $caja02->id,
            'amount' => '100.00',
            'reason' => 'Transferencia de prueba (DEMO-TRANSFER-CANCELLED).',
        ], $manager, $branch));

        $this->at($today->copy()->setTime(9, 15), fn () => app(CashTransferService::class)
            ->cancel($transferCancelled, 'Registrada por error, se anula.', $manager));

        $this->at($today->copy()->setTime(11, 0), fn () => app(CashTransferService::class)->create([
            'source_session_id' => $caja01Shift4->id,
            'target_box_id' => $caja02->id,
            'amount' => '180.00',
            'reason' => 'Fondo para cambio en Miraflores (DEMO-TRANSFER-PENDING).',
        ], $manager, $branch));
        // Esta última queda "pending": todavía nadie la aprueba ni la rechaza,
        // tal como una transferencia recién enviada y en tránsito.
    }

    private function closeExact(CashSession $session, Carbon $closedAt, User $user): void
    {
        $this->at($closedAt, function () use ($session, $user) {
            $expected = (float) app(CashSessionSummaryService::class)->expectedCash($session);
            app(CashSessionService::class)->close($session, null, $expected, null, $user);
        });
    }

    private function closeWithDifference(CashSession $session, Carbon $closedAt, float $difference, string $notes, User $user): void
    {
        $this->at($closedAt, function () use ($session, $difference, $notes, $user) {
            $expected = (float) app(CashSessionSummaryService::class)->expectedCash($session);
            app(CashSessionService::class)->close($session, null, round($expected + $difference, 2), $notes, $user);
        });
    }

    private function mainWarehouseId(Branch $branch): ?string
    {
        $warehouse = Warehouse::where('branch_id', $branch->id)->where('code', 'ALM01')->first()
            ?? Warehouse::where('branch_id', $branch->id)->where('is_default', true)->first();

        return $warehouse?->id;
    }

    private function bankAccountId(string $alias): string
    {
        return BankAccount::where('alias', $alias)->firstOrFail()->id;
    }

    private function paymentMethodId(string $code): string
    {
        return PaymentMethod::where('code', $code)->firstOrFail()->id;
    }

    /**
     * @param  Collection<string, CatalogItem>  $catalog
     * @param  list<array{code: string, qty: float}>  $lines
     * @return array{0: list<array<string, mixed>>|null, 1: string}
     */
    private function buildLines(Collection $catalog, array $lines, Currency $currency): array
    {
        $pricing = app(SalePricingService::class);
        $items = [];
        $lineInputs = [];

        foreach ($lines as $line) {
            /** @var CatalogItem|null $item */
            $item = $catalog->get($line['code']);
            /** @var ItemPresentation|null $pres */
            $pres = $item?->defaultPresentation;

            if ($item === null || $pres === null) {
                return [null, '0.00'];
            }

            $unitPrice = $pricing->listPrice($pres, PriceType::Retail, $line['qty'], $currency, '1');
            if ($unitPrice === null) {
                return [null, '0.00'];
            }

            $items[] = [
                'item_presentation_id' => $pres->id,
                'quantity' => (string) $line['qty'],
                'unit_price' => $unitPrice,
            ];

            $lineInputs[] = new LineInput(
                quantity: (string) $line['qty'],
                unitPrice: $unitPrice,
                discountPercent: '0.00',
                taxType: $item->tax_type,
            );
        }

        $totals = (new DocumentTotalsCalculator)->calculate($lineInputs, '18.00', true);

        return [$items, (string) $totals->total];
    }
}
