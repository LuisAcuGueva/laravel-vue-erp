<?php

declare(strict_types=1);

namespace App\Modules\Configuration\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Configuration\Requests\CompanyLogoRequest;
use App\Modules\Configuration\Requests\CompanySettingRequest;
use App\Modules\Configuration\Resources\CompanySettingResource;
use App\Modules\Configuration\Resources\CompanySummaryResource;
use App\Modules\Configuration\Services\CompanySettingService;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class CompanySettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CompanySettingService $companyService,
    ) {}

    public function show(): JsonResponse
    {
        $company = $this->companyService->current();

        return $this->successResponse(
            data: new CompanySettingResource($company),
        );
    }

    public function update(CompanySettingRequest $request): JsonResponse
    {
        $company = $this->companyService->update($request->toAttributes());

        return $this->successResponse(
            data: new CompanySettingResource($company),
        );
    }

    public function uploadLogo(CompanyLogoRequest $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('logo');
        $company = $this->companyService->replaceLogo($file);

        return $this->successResponse(
            data: new CompanySettingResource($company),
        );
    }

    public function summary(): JsonResponse
    {
        $company = $this->companyService->current();

        return $this->successResponse(
            data: new CompanySummaryResource($company),
        );
    }
}
