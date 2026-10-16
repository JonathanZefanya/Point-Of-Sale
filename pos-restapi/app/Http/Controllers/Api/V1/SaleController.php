<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\HeldSalesRequest;
use App\Http\Requests\Sale\HoldSaleRequest;
use App\Http\Requests\Sale\IndexSaleRequest;
use App\Http\Requests\Sale\QuoteSaleRequest;
use App\Http\Requests\Sale\RefundSaleRequest;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Requests\Sale\SyncSalesRequest;
use App\Http\Requests\Sale\VoidSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Outlet;
use App\Models\Sale;
use App\Services\Sales\OfflineSyncService;
use App\Services\Sales\ReceiptBuilder;
use App\Services\Sales\SaleQueryService;
use App\Services\Sales\SaleQuoter;
use App\Services\Sales\SaleRefundService;
use App\Services\Sales\SaleService;
use App\Services\Sales\SaleVoidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    private const DETAIL = ['items', 'payments', 'outlet', 'user', 'customer', 'voider', 'refunds.items', 'refunds.approver'];

    public function __construct(
        private readonly SaleService $sales,
        private readonly SaleQueryService $query,
    ) {}

    /** Riwayat transaksi (tanpa transaksi tersimpan). */
    public function index(IndexSaleRequest $request): AnonymousResourceCollection
    {
        return SaleResource::collection($this->query->history($request->user(), $request->validated()));
    }

    /** 201 untuk transaksi baru; 200 bila idempotency key sudah pernah dipakai (transaksi yang sama). */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        [$sale, $created] = $this->sales->checkout($request->user(), $request->validated());

        return (new SaleResource($sale))->response()->setStatusCode($created ? 201 : 200);
    }

    /** Sinkron transaksi offline (BE-3.2): hasil per transaksi — created | duplicate | failed. */
    public function sync(SyncSalesRequest $request, OfflineSyncService $sync): JsonResponse
    {
        return response()->json(['data' => $sync->sync($request->user(), $request->input('sales'))]);
    }

    /** Total keranjang versi server (promo, voucher, diskon manual, pajak) untuk ditampilkan di kasir. */
    public function quote(QuoteSaleRequest $request, SaleQuoter $quoter): JsonResponse
    {
        $data = $request->validated();
        $outlet = Outlet::query()->findOrFail($data['outlet_id']);
        $quote = $quoter->quote($outlet, $data['items'], $request->user(), $data['voucher_code'] ?? null, $data['manual_discount'] ?? null);

        return response()->json(['data' => [
            ...$quote['calc']->totals(),
            'lines' => array_map(fn ($l) => ['unit_price' => $l['unit_price'], 'discount' => $l['discount'], 'total' => $l['total']], $quote['calc']->lines),
            'promotions' => $quote['applied'],
            'manual_discount' => $quote['manual_discount'],
            'needs_supervisor' => $quote['needs_supervisor'],
            'voucher' => $quote['voucher'] ? ['code' => $quote['voucher']->code, 'name' => $quote['voucher']->promotion->name] : null,
        ]]);
    }

    public function show(Sale $sale): SaleResource
    {
        Gate::authorize('view', $sale);

        return new SaleResource($sale->load(self::DETAIL));
    }

    /** Refund sebagian/penuh (F5) — disetujui PIN supervisor. */
    public function refund(RefundSaleRequest $request, Sale $sale, SaleRefundService $refunds): SaleResource
    {
        $refunds->refund(
            $sale,
            $request->user(),
            $request->validated('items'),
            PaymentMethod::from($request->validated('method')),
            $request->validated('reason'),
            $request->validated('supervisor_pin'),
        );

        return new SaleResource($sale->fresh(self::DETAIL));
    }

    /** Data struk siap cetak (juga untuk cetak ulang). */
    public function receipt(Sale $sale, ReceiptBuilder $receipts): JsonResponse
    {
        Gate::authorize('view', $sale);
        abort_if($sale->status === SaleStatus::Held, 404);

        return response()->json(['data' => $receipts->build($sale)]);
    }

    public function held(HeldSalesRequest $request): AnonymousResourceCollection
    {
        return SaleResource::collection($this->query->held((int) $request->validated('outlet_id')));
    }

    public function hold(HoldSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->hold($request->user(), $request->validated());

        return (new SaleResource($sale))->response()->setStatusCode(201);
    }

    public function discardHeld(Sale $sale): Response
    {
        Gate::authorize('view', $sale);
        abort_unless($sale->status === SaleStatus::Held, 404);
        $this->sales->discardHeld($sale);

        return response()->noContent();
    }

    public function void(VoidSaleRequest $request, Sale $sale, SaleVoidService $voids): SaleResource
    {
        return new SaleResource($voids->void($sale, $request->user(), $request->validated('reason'), $request->validated('supervisor_pin')));
    }
}
