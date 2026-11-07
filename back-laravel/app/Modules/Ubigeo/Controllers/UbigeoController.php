<?php

declare(strict_types=1);

namespace App\Modules\Ubigeo\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Ubigeo\Resources\UbigeoResource;
use App\Modules\Ubigeo\Services\UbigeoService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UbigeoController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UbigeoService $ubigeoService,
    ) {}

    /**
     * Catálogo completo de distritos, cacheable con `ETag`.
     */
    public function index(Request $request): JsonResponse
    {
        $version = $this->ubigeoService->version();

        $response = $this->successResponse(
            data: UbigeoResource::collection($this->ubigeoService->catalog()->values()),
            meta: ['version' => $version],
        );

        $response->setEtag($version)->setPrivate()->setMaxAge(86400);
        $response->isNotModified($request);

        return $response;
    }
}
