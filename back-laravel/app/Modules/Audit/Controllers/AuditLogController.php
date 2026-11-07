<?php

declare(strict_types=1);

namespace App\Modules\Audit\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Requests\ListAuditLogsRequest;
use App\Modules\Audit\Resources\AuditLogResource;
use App\Modules\Audit\Services\AuditLogService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(ListAuditLogsRequest $request): JsonResponse
    {
        $paginator = $this->auditLogService->list($request->validated());

        return $this->successResponse(
            data: AuditLogResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }

    public function subjectTypes(): JsonResponse
    {
        return $this->successResponse($this->auditLogService->subjectTypes());
    }

    public function show(string $id): JsonResponse
    {
        $activity = $this->auditLogService->findById($id);

        if ($activity === null) {
            return $this->errorResponse("Registro de auditoría no encontrado: {$id}", 404);
        }

        return $this->successResponse(new AuditLogResource($activity));
    }
}
