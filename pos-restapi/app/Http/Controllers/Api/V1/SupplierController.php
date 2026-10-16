<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SupplierController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->role->canManageStock(), 403);
        $search = trim((string) $request->query('search'));
        $open = [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Partial];

        $suppliers = Supplier::query()
            ->withCount(['purchaseOrders as open_orders_count' => fn ($q) => $q->whereIn('status', $open)])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(min(100, (int) $request->query('per_page', 20)));

        return SupplierResource::collection($suppliers);
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        return (new SupplierResource(Supplier::query()->create($request->validated())))->response()->setStatusCode(201);
    }

    public function update(SupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }

    /** Soft delete: PO lama tetap menampilkan nama supplier. */
    public function destroy(Request $request, Supplier $supplier): Response
    {
        abort_unless($request->user()->role->canManageStock(), 403);
        $supplier->delete();

        return response()->noContent();
    }
}
