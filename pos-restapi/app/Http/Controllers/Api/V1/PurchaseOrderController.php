<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\IndexPurchaseOrderRequest;
use App\Http\Requests\Purchase\PurchaseOrderRequest;
use App\Http\Requests\Purchase\ReceivePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $orders) {}

    public function index(IndexPurchaseOrderRequest $request): AnonymousResourceCollection
    {
        $f = $request->validated();

        return PurchaseOrderResource::collection(PurchaseOrder::query()
            ->visibleTo($request->user())
            ->with('supplier', 'outlet')->withCount('items')
            ->when($f['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$s}%"))))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['supplier_id'] ?? null, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($f['outlet_id'] ?? null, fn ($q, $id) => $q->where('outlet_id', $id))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')
            ->paginate($f['per_page'] ?? 20));
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $po = $this->orders->save(new PurchaseOrder, $request->validated(), $request->user());

        return (new PurchaseOrderResource($po->load('user')))->response()->setStatusCode(201);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorizeAccess($request, $purchaseOrder);

        return new PurchaseOrderResource($purchaseOrder->load('items', 'supplier', 'outlet', 'user'));
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorizeAccess($request, $purchaseOrder);

        return new PurchaseOrderResource($this->orders->save($purchaseOrder, $request->validated(), $request->user())->load('user'));
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $po = $this->orders->receive($purchaseOrder, $request->validated('items'), $request->user(), $request->validated('note'));

        return new PurchaseOrderResource($po->load('user'));
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $this->authorizeAccess($request, $purchaseOrder);

        return new PurchaseOrderResource($this->orders->cancel($purchaseOrder)->load('user'));
    }

    private function authorizeAccess(Request $request, PurchaseOrder $po): void
    {
        abort_unless($request->user()->role->canManageStock() && $request->user()->canAccessOutlet($po->outlet_id), 403);
    }
}
