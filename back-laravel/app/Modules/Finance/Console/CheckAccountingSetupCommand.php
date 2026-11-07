<?php

declare(strict_types=1);

namespace App\Modules\Finance\Console;

use App\Modules\Finance\Services\AccountingSetupHealthService;
use Illuminate\Console\Command;

class CheckAccountingSetupCommand extends Command
{
    protected $signature = 'finance:check-accounting-setup {--json : Imprime el diagnóstico en JSON}';

    protected $description = 'Diagnostica la configuración contable de Finanzas (plan de cuentas y conceptos)';

    public function handle(AccountingSetupHealthService $health): int
    {
        $payload = $health->payload();

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $payload['status'] === 'error' ? self::FAILURE : self::SUCCESS;
        }

        if ($payload['issues'] === []) {
            $this->info('La configuración contable es consistente.');

            return self::SUCCESS;
        }

        $this->table(
            ['Severidad', 'Código', 'Cantidad', 'Mensaje'],
            array_map(fn (array $i): array => [$i['severity'], $i['code'], $i['count'], $i['message']], $payload['issues']),
        );

        foreach ($payload['issues'] as $issue) {
            $this->newLine();
            $this->line("<options=bold>{$issue['code']}</>");
            foreach (array_slice($issue['items'], 0, 10) as $item) {
                $this->line("  - {$item['code']} · {$item['name']}");
            }
        }

        if ($payload['status'] === 'error') {
            $this->error('Se encontraron errores en la configuración contable.');

            return self::FAILURE;
        }

        $this->warn('La configuración contable tiene advertencias.');

        return self::SUCCESS;
    }
}
