<?php

declare(strict_types=1);

namespace App\Modules\Users\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Exports\UserCsvExport;
use App\Modules\Users\Models\User;
use App\Modules\Users\Requests\ListUsersRequest;
use App\Modules\Users\Requests\UserRequest;
use App\Modules\Users\Resources\UserResource;
use App\Modules\Users\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $users = $this->userService->list($request->validated());

        return UserResource::collection($users);
    }

    public function export(ListUsersRequest $request): StreamedResponse
    {
        return app(UserCsvExport::class)->download(
            $this->userService->query($request->validated()),
            'usuarios',
        );
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = $this->userService->create(
            $request->toAttributes(),
            $request->roles() ?? [],
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $id): UserResource|JsonResponse
    {
        $user = $this->userService->findById($id);

        if (! $user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        return new UserResource($user);
    }

    public function update(UserRequest $request, string $id): UserResource|JsonResponse
    {
        $user = $this->userService->findById($id);

        if (! $user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $updatedUser = $this->userService->update(
            $user,
            $request->toAttributes(),
            $request->roles(),
        );

        return new UserResource($updatedUser);
    }

    public function destroy(Request $request, string $id): Response|JsonResponse
    {
        $user = $this->userService->findById($id);

        if (! $user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        $this->userService->delete($user, $actor);

        return response()->noContent();
    }
}
