<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyStockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:verify-stock {--fix : Report fix plan without modifying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la consistencia entre stocks.quantity y la suma de movimientos de kardex';

    public function handle(): int
    {
        $this->info('Verificando consistencia de stock vs kardex...');

        $kardexSums = DB::table('kardex_movements')
            ->select('warehouse_id', 'catalog_item_id')
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) as kardex_quantity")
            ->groupBy('warehouse_id', 'catalog_item_id')
            ->get()
            ->keyBy(fn ($row) => $row->warehouse_id.':'.$row->catalog_item_id);

        $stocks = DB::table('stocks')
            ->select('warehouse_id', 'catalog_item_id', 'quantity')
            ->get()
            ->keyBy(fn ($row) => $row->warehouse_id.':'.$row->catalog_item_id);

        $allKeys = $kardexSums->keys()->merge($stocks->keys())->unique();

        $discrepancies = [];

        foreach ($allKeys as $key) {
            $stockQty = (float) ($stocks->get($key)->quantity ?? 0.0);
            $kardexQty = (float) ($kardexSums->get($key)->kardex_quantity ?? 0.0);
            $diff = round($stockQty - $kardexQty, 4);

            if (abs($diff) > 0.0001) {
                [$warehouseId, $catalogItemId] = explode(':', (string) $key);
                $discrepancies[] = [
                    'warehouse_id' => $warehouseId,
                    'catalog_item_id' => $catalogItemId,
                    'stock_qty' => number_format($stockQty, 4),
                    'kardex_qty' => number_format($kardexQty, 4),
                    'difference' => number_format($diff, 4),
                ];
            }
        }

        if (empty($discrepancies)) {
            $this->info('Sin diferencias entre stock y kardex.');

            return self::SUCCESS;
        }

        $this->table(
            ['Almacén ID', 'Ítem ID', 'Stock', 'Kardex', 'Diferencia'],
            $discrepancies
        );

        $count = count($discrepancies);

        if ($this->option('fix')) {
            $this->warn("Modo --fix: Se detectaron {$count} discrepancias. Corrección automática no implementada en esta versión.");
        } else {
            $this->error("Se encontraron {$count} discrepancias entre stock y kardex.");
        }

        return self::FAILURE;
    }
}
