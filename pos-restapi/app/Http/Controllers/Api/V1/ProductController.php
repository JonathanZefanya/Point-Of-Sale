<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        if (isset($filters['outlet_id']) && ! $request->user()->canAccessOutlet((int) $filters['outlet_id'])) {
            unset($filters['outlet_id']);
        }

        return ProductResource::collection($this->products->paginate($filters));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load(ProductService::RELATIONS));
    }

    public function barcode(Request $request, string $code): ProductResource
    {
        Gate::authorize('viewAny', Product::class);
        $outletId = (int) $request->query('outlet_id') ?: null;

        return new ProductResource($this->products->findForScan($code, $outletId && $request->user()->canAccessOutlet($outletId) ? $outletId : null));
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        return new ProductResource($this->products->update($product, $request->validated()));
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);
        $this->products->delete($product);

        return response()->noContent();
    }
}
