<?php

declare(strict_types=1);

namespace App\Modules\Sales\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clients\Models\Client;
use App\Modules\Sales\Services\ReceivableQueryService;
use App\Support\Http\ApiResponse;
use App\Support\Pdf\PdfRenderer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientStatementController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ReceivableQueryService $queryService,
        private readonly PdfRenderer $pdfRenderer,
    ) {}

    /**
     * Consulta el estado de cuenta de un cliente.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Client $client */
        $client = Client::whereKey($id)->firstOrFail();

        $from = (string) $request->input('from', Carbon::today()->startOfMonth()->toDateString());
        $to = (string) $request->input('to', Carbon::today()->toDateString());
        $currencyId = $request->input('currency_id');

        $statement = $this->queryService->statement($client, $from, $to, $currencyId ? (string) $currencyId : null);

        return $this->successResponse(data: $statement);
    }

    /**
     * Genera el estado de cuenta en formato PDF.
     */
    public function pdf(Request $request, string $id): Response
    {
        /** @var Client $client */
        $client = Client::whereKey($id)->firstOrFail();

        $from = (string) $request->input('from', Carbon::today()->startOfMonth()->toDateString());
        $to = (string) $request->input('to', Carbon::today()->toDateString());
        $currencyId = $request->input('currency_id');

        $statement = $this->queryService->statement($client, $from, $to, $currencyId ? (string) $currencyId : null);

        $safeClientName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $client->name);

        return $this->pdfRenderer->render('pdf.sales.client-statement', $statement, [
            'filename' => "estado-de-cuenta-{$safeClientName}.pdf",
        ]);
    }
}
