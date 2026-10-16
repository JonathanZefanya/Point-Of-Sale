<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\PinLoginRequest;
use App\Http\Requests\Auth\PinUsersRequest;
use App\Http\Resources\AuthTokenResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function login(LoginRequest $request): AuthTokenResource
    {
        $result = $this->auth->loginWithPassword($request->validated('email'), $request->validated('password'), $request->ip());

        return new AuthTokenResource($result);
    }

    public function pinLogin(PinLoginRequest $request): AuthTokenResource
    {
        $result = $this->auth->loginWithPin((int) $request->validated('user_id'), $request->validated('pin'));

        return new AuthTokenResource($result);
    }

    /** Daftar kasir untuk layar "pilih nama" sebelum memasukkan PIN (publik). */
    public function pinUsers(PinUsersRequest $request): JsonResponse
    {
        $users = $this->auth->pinUsersForOutlet($request->validated('outlet_code'));

        return response()->json(['data' => $users->map->only(['id', 'name'])->values()]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('outlets'));
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($request->user());

        return response()->noContent();
    }
}
