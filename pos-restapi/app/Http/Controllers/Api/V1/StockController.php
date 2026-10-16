<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdjustmentReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\IndexStockRequest;
use App\Http\Requests\Stock\StockAdjustmentRequest;
use App\Http\Requests\Stock\StockMovementsRequest;
use App\Http\Resources\StockMovementResource;
use App\Http\Resources\StockResource;
use App\Models\Product;
use App\Services\StockAdjustmentService;
use App\Services\StockQueryService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockController extends Controller
{
    public function __construct(private readonly StockQueryService $query) {}

    /** Daftar stok per outlet + ringkasan jumlah menipis/habis. */
    public function index(IndexStockRequest $request): AnonymousResourceCollection
    {
        $outletId = (int) $request->validated('outlet_id');

        return StockResource::collection($this->query->paginate($request->validated()))
            ->additional(['summary' => [...$this->query->summary($outletId), 'outlet_id' => $outletId]]);
    }

    /** Kartu stok satu produk di satu outlet. */
    public function movements(StockMovementsRequest $request, Product $product, StockService $stock): AnonymousResourceCollection
    {
        $outletId = (int) $request->validated('outlet_id');
        $variant = $request->validated('variant_id') ? $product->variants()->find($request->validated('variant_id')) : null;

        return StockMovementResource::collection($this->query->movements($product, $request->validated()))
            ->additional(['product' => [
                'id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $variant ? "{$product->name} - {$variant->name}" : $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'unit' => $product->unit,
                'outlet_id' => $outletId,
                'quantity' => $stock->quantity($outletId, $product->id, $variant?->id),
            ]]);
    }

    public function adjust(StockAdjustmentRequest $request, StockAdjustmentService $adjustments): JsonResponse
    {
        $movement = $adjustments->adjust(
            (int) $request->validated('outlet_id'),
            $request->product(),
            (int) $request->validated('quantity_change'),
            AdjustmentReason::from($request->validated('reason')),
            $request->validated('note'),
            $request->validated('variant_id') ? (int) $request->validated('variant_id') : null,
        );

        return (new StockMovementResource($movement->load('user:id,name')))->response()->setStatusCode(201);
    }
}
