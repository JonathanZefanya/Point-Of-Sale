<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ModifierGroup\ModifierGroupRequest;
use App\Http\Resources\ModifierGroupResource;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Services\ModifierGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ModifierGroupController extends Controller
{
    public function __construct(private readonly ModifierGroupService $groups) {}

    public function index(): AnonymousResourceCollection
    {
        return ModifierGroupResource::collection(ModifierGroup::query()->with('modifiers')->withCount('products')->orderBy('name')->get());
    }

    public function store(ModifierGroupRequest $request): JsonResponse
    {
        return (new ModifierGroupResource($this->groups->save(new ModifierGroup, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(ModifierGroupRequest $request, ModifierGroup $modifierGroup): ModifierGroupResource
    {
        return new ModifierGroupResource($this->groups->save($modifierGroup, $request->validated()));
    }

    public function destroy(ModifierGroup $modifierGroup): Response
    {
        Gate::authorize('delete', Product::class);
        $modifierGroup->delete(); // pivot & pilihan ikut terhapus (cascade); penjualan lama memakai snapshot

        return response()->noContent();
    }
}
