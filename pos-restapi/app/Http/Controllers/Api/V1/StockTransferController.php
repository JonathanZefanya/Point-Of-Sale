<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ReceiveStockTransferRequest;
use App\Http\Requests\Stock\StockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\StockTransfer;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockTransferController extends Controller
{
    private const DETAIL = ['fromOutlet', 'toOutlet', 'user', 'receiver', 'items'];

    public function __construct(private readonly StockTransferService $transfers) {}

    /** ?outlet_id=&direction=in|out&status= — default semua transfer yang melibatkan outlet user. */
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->role->canManageStock(), 403);
        $outletId = (int) $request->query('outlet_id');
        $direction = $request->query('direction');

        return StockTransferResource::collection(StockTransfer::query()
            ->visibleTo($request->user())
            ->with('fromOutlet', 'toOutlet')
            ->withCount('items')->withSum('items as total_quantity', 'quantity')
            ->when($outletId && $direction === 'in', fn ($q) => $q->where('to_outlet_id', $outletId))
            ->when($outletId && $direction === 'out', fn ($q) => $q->where('from_outlet_id', $outletId))
            ->when($outletId && ! in_array($direction, ['in', 'out'], true), fn ($q) => $q->where(fn ($w) => $w->where('from_outlet_id', $outletId)->orWhere('to_outlet_id', $outletId)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')
            ->paginate(min(100, (int) $request->query('per_page', 20))));
    }

    public function store(StockTransferRequest $request): JsonResponse
    {
        $transfer = $this->transfers->create($request->validated(), $request->user());

        return (new StockTransferResource($transfer->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    public function show(Request $request, StockTransfer $stockTransfer): StockTransferResource
    {
        $user = $request->user();
        abort_unless($user->role->canManageStock() && ($user->canAccessOutlet($stockTransfer->from_outlet_id) || $user->canAccessOutlet($stockTransfer->to_outlet_id)), 403);

        return new StockTransferResource($stockTransfer->load(self::DETAIL));
    }

    /** Hanya outlet tujuan yang menerima (dicek di ReceiveStockTransferRequest). */
    public function receive(ReceiveStockTransferRequest $request, StockTransfer $stockTransfer): StockTransferResource
    {
        $transfer = $this->transfers->receive($stockTransfer, $request->validated('items'), $request->user(), $request->validated('note'));

        return new StockTransferResource($transfer->load(self::DETAIL));
    }

    /** Hanya outlet asal yang membatalkan, selama belum diterima. */
    public function cancel(Request $request, StockTransfer $stockTransfer): StockTransferResource
    {
        abort_unless($request->user()->role->canManageStock() && $request->user()->canAccessOutlet($stockTransfer->from_outlet_id), 403);

        return new StockTransferResource($this->transfers->cancel($stockTransfer)->load(self::DETAIL));
    }
}
