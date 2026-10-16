<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Outlet\IndexOutletRequest;
use App\Http\Requests\Outlet\OutletRequest;
use App\Http\Resources\OutletResource;
use App\Models\Outlet;
use App\Services\OutletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OutletController extends Controller
{
    public function __construct(private readonly OutletService $outlets) {}

    /** Owner: semua outlet. Role lain: outlet yang ditugaskan; ?all=1 untuk memilih tujuan transfer stok. */
    public function index(IndexOutletRequest $request): AnonymousResourceCollection
    {
        $outlets = Outlet::query()
            ->when(! ($request->boolean('all') && $request->user()->role->canManageStock()), fn ($q) => $q->visibleTo($request->user()))
            ->when($request->validated('search'), fn ($q, $s) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%")
            ))
            ->orderBy('code')
            ->paginate($request->validated('per_page') ?? 50);

        return OutletResource::collection($outlets);
    }

    public function store(OutletRequest $request): JsonResponse
    {
        return (new OutletResource($this->outlets->save(new Outlet, $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Request $request, Outlet $outlet): OutletResource
    {
        abort_unless($request->user()->canAccessOutlet($outlet->id), 403);

        return new OutletResource($outlet);
    }

    public function update(OutletRequest $request, Outlet $outlet): OutletResource
    {
        return new OutletResource($this->outlets->save($outlet, $request->validated()));
    }

    public function destroy(Request $request, Outlet $outlet): Response
    {
        abort_unless($request->user()->role === Role::Owner, 403);
        $this->outlets->delete($outlet);

        return response()->noContent();
    }
}
