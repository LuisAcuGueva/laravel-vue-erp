<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Maintenance\Models\Branch;
use App\Modules\Maintenance\Services\UserBranchService;
use App\Modules\Users\Models\User;
use App\Support\Audit\AuditContext;
use App\Support\Branch\ActiveBranch;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveActiveBranch
{
    public function __construct(
        private readonly UserBranchService $userBranchService,
        private readonly ActiveBranch $activeBranch,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $branchId = $request->header('X-Branch-Id');

        /** @var Collection<int, Branch> $accessibleBranches */
        $accessibleBranches = $this->userBranchService->accessibleBranches($user);

        /** @var Branch|null $branch */
        $branch = null;

        if ($branchId !== null && $branchId !== '') {
            if (! Str::isUuid($branchId)) {
                return response()->json([
                    'message' => 'No tiene acceso a la sede seleccionada.',
                ], 403);
            }

            $branch = $accessibleBranches->firstWhere('id', $branchId);

            if ($branch === null) {
                return response()->json([
                    'message' => 'No tiene acceso a la sede seleccionada.',
                ], 403);
            }
        } else {
            $default = $user->defaultBranch();
            if ($default !== null && $default->is_active && $accessibleBranches->contains('id', $default->id)) {
                $branch = $default;
            } elseif ($user->isAdmin()) {
                $branch = $accessibleBranches->firstWhere('is_main', true) ?? $accessibleBranches->first();
            }

            if ($branch === null) {
                return response()->json([
                    'message' => 'El usuario no tiene sedes asignadas.',
                ], 409);
            }
        }

        $this->activeBranch->set($branch);
        app(AuditContext::class)->setBranchId($branch->id);

        return $next($request);
    }
}
