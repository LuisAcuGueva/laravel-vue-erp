<?php

declare(strict_types=1);

namespace App\Modules\Maintenance\Resources;

use App\Modules\Maintenance\Models\Employee;
use App\Support\Identity\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Employee $employee */
        $employee = $this->resource;

        return [
            'id' => $employee->id,
            'document_type' => $employee->document_type instanceof DocumentType
                ? $employee->document_type->value
                : (string) $employee->document_type,
            'document_number' => $employee->document_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'full_name' => $employee->full_name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'address' => $employee->address,
            'birth_date' => $employee->birth_date?->format('Y-m-d'),
            'hire_date' => $employee->hire_date?->format('Y-m-d'),
            'position' => $employee->position,
            'area_id' => $employee->area_id,
            'branch_id' => $employee->branch_id,
            'user_id' => $employee->user_id,
            'is_active' => $employee->is_active,
            'area' => $this->whenLoaded('area', fn () => $employee->area ? [
                'id' => $employee->area->id,
                'name' => $employee->area->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $employee->branch ? [
                'id' => $employee->branch->id,
                'code' => $employee->branch->code,
                'name' => $employee->branch->name,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $employee->user ? [
                'id' => $employee->user->id,
                'email' => $employee->user->email,
                'status' => $employee->user->status,
            ] : null),
            'created_at' => $employee->created_at?->toIso8601String(),
            'updated_at' => $employee->updated_at?->toIso8601String(),
        ];
    }
}
