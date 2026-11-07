<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Clients\Models\Client;
use App\Modules\Finance\Models\Currency;
use App\Modules\Inventory\Models\CatalogItem;
use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Models\Employee;
use App\Modules\Sales\Models\Quotation;
use App\Modules\Sales\Services\QuotationService;
use App\Modules\Sales\Services\QuotationWorkflowService;
use App\Modules\Users\Models\User;
use Database\Seeders\Concerns\TravelsInTime;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Crea 3 cotizaciones demo con las 3 trayectorias reales del flujo: una vencida
 * (nunca respondida), una aceptada y lista para convertirse en venta (la toma
 * CommercialOperationsDemoSeeder para emitir la Venta A), y una enviada que
 * queda abierta. Usa TravelsInTime para fechas de negocio realistas: creación,
 * envío y vencimiento en días distintos, no todo "hoy".
 * Es idempotente.
 *
 * Depende de: CatalogSeeder, ClientSeeder, EmployeeSeeder, BranchSeeder.
 */
class QuotationsDemoSeeder extends Seeder
{
    use TravelsInTime;

    public function run(): void
    {
        if (Quotation::where('number', 'like', 'COT-%')->exists()) {
            return;
        }

        $branch = Branch::where('is_main', true)->first() ?? Branch::first();
        $user = User::where('email', 'user@example.com')->first();
        $seller = Employee::where('document_number', '70123456')->first();
        $pen = Currency::base() ?? Currency::where('code', 'PEN')->first();

        if ($branch === null || $user === null || $pen === null) {
            return;
        }

        $catalog = CatalogItem::with('defaultPresentation')->get()->keyBy('code');
        $service = app(QuotationService::class);
        $workflow = app(QuotationWorkflowService::class);

        $today = now();

        // =========================================================================
        // COT-000001: Roberto Díaz — nunca respondida, vence sin aceptarse.
        // =========================================================================
        $roberto = Client::where('document_number', '10434567891')->first();
        if ($roberto !== null) {
            $quotation1 = $this->at($today->copy()->subDays(15)->setTime(9, 0), fn () => $service->create([
                'client_id' => $roberto->id,
                'currency_id' => $pen->id,
                'seller_id' => $seller?->id,
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(3)->toDateString(),
                'items' => $this->buildItems($catalog, [
                    ['code' => 'ITM-000007', 'qty' => 1.0],
                    ['code' => 'ITM-000009', 'qty' => 2.0],
                ]),
                'notes' => 'Cotización demo que vence sin respuesta (DEMO-QUOTE-EXPIRED).',
            ], $user, $branch));

            $this->at($today->copy()->subDays(15)->setTime(9, 5), fn () => $workflow->send($quotation1, $user));

            // 4 días después de vencida, el job de vencimiento la marca "expired".
            $this->at($today->copy()->subDays(11)->setTime(9, 0), fn () => $workflow->expireOverdue());
        }

        // =========================================================================
        // COT-000002: Juan Carlos Pérez — aceptada; Venta A la convierte después.
        // =========================================================================
        $juanPerez = Client::where('document_number', '41234567')->first();
        if ($juanPerez !== null) {
            $quotation2 = $this->at($today->copy()->subDays(10)->setTime(9, 0), fn () => $service->create([
                'client_id' => $juanPerez->id,
                'currency_id' => $pen->id,
                'seller_id' => $seller?->id,
                'issue_date' => now()->toDateString(),
                'items' => $this->buildItems($catalog, [
                    ['code' => 'ITM-000001', 'qty' => 1.0],
                    ['code' => 'ITM-000004', 'qty' => 1.0],
                ]),
                'notes' => 'Cotización demo aceptada, pendiente de convertir en venta (DEMO-QUOTE-CONVERTED).',
            ], $user, $branch));

            $this->at($today->copy()->subDays(10)->setTime(9, 5), fn () => $workflow->send($quotation2, $user));
            $this->at($today->copy()->subDays(9)->setTime(10, 0), fn () => $workflow->accept($quotation2, $user));
        }

        // =========================================================================
        // COT-000003: María Elena Flores — enviada, queda abierta (sin resolver).
        // =========================================================================
        $mariaFlores = Client::where('document_number', '42345678')->first();
        if ($mariaFlores !== null) {
            $quotation3 = $this->at($today->copy()->subDays(3)->setTime(9, 30), fn () => $service->create([
                'client_id' => $mariaFlores->id,
                'currency_id' => $pen->id,
                'seller_id' => $seller?->id,
                'issue_date' => now()->toDateString(),
                'items' => $this->buildItems($catalog, [
                    ['code' => 'ITM-000005', 'qty' => 1.0],
                    ['code' => 'ITM-000006', 'qty' => 1.0],
                ]),
                'notes' => 'Cotización demo enviada, sin resolver aún (DEMO-QUOTE-OPEN).',
            ], $user, $branch));

            $this->at($today->copy()->subDays(3)->setTime(9, 45), fn () => $workflow->send($quotation3, $user));
        }
    }

    /**
     * @param  Collection<string, CatalogItem>  $catalog
     * @param  list<array{code: string, qty: float}>  $lines
     * @return list<array<string, mixed>>
     */
    private function buildItems(Collection $catalog, array $lines): array
    {
        $items = [];

        foreach ($lines as $line) {
            /** @var CatalogItem|null $item */
            $item = $catalog->get($line['code']);
            $pres = $item?->defaultPresentation;

            if ($item === null || $pres === null) {
                continue;
            }

            $items[] = [
                'item_presentation_id' => $pres->id,
                'quantity' => (string) $line['qty'],
            ];
        }

        return $items;
    }
}
