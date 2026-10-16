<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(IndexUserRequest $request): AnonymousResourceCollection
    {
        return UserResource::collection($this->users->paginate($request->user(), $request->validated()));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->users->create($request->user(), $request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(Request $request, User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('outlets'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->update($request->user(), $user, $request->validated()));
    }

    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);
        $this->users->delete($user);

        return response()->noContent();
    }

    /** Daftar peran + penanda apakah pengguna saat ini boleh memberikannya. */
    public function roles(Request $request): JsonResponse
    {
        $assignable = $request->user()->role->assignableRoles();

        return response()->json(['data' => array_map(fn (Role $role) => [
            'value' => $role->value,
            'label' => $role->label(),
            'assignable' => in_array($role, $assignable, true),
        ], Role::cases())]);
    }
}
