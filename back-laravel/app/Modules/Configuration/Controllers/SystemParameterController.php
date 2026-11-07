<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Configuration\Requests\ListSystemParametersRequest;
use App\Modules\Configuration\Requests\UpdateSystemParameterRequest;
use App\Modules\Configuration\Resources\SystemParameterResource;
use App\Modules\Configuration\Services\SystemParameterService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;

class SystemParameterController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SystemParameterService $parameterService,
    ) {}

    public function index(ListSystemParametersRequest $request): JsonResponse
    {
        $group = $request->input('group');
        $params = $this->parameterService->list(is_string($group) ? $group : null);

        return $this->successResponse(
            data: SystemParameterResource::collection($params),
        );
    }

    public function update(string $id, UpdateSystemParameterRequest $request): JsonResponse
    {
        $param = $this->parameterService->findById($id);

        if ($param === null) {
            return $this->errorResponse("Parámetro no encontrado: {$id}", 404);
        }

        $updated = $this->parameterService->update($param, $request->toValue());

        return $this->successResponse(
            data: new SystemParameterResource($updated),
        );
    }
}
