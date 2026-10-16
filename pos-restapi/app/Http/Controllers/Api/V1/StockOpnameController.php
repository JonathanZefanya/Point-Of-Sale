<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\SaveOpnameCountsRequest;
use App\Http\Requests\Stock\StartOpnameRequest;
use App\Http\Resources\StockOpnameResource;
use App\Models\StockOpname;
use App\Services\StockOpnameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockOpnameController extends Controller
{
    private const DETAIL = ['outlet', 'category', 'user', 'applier'];

    public function __construct(private readonly StockOpnameService $opnames) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->role->canManageStock(), 403);

        return StockOpnameResource::collection(StockOpname::query()
            ->visibleTo($request->user())
            ->with('outlet', 'category', 'user')
            ->withCount(['items', 'items as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity')])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('outlet_id'), fn ($q, $id) => $q->where('outlet_id', $id))
            ->latest('id')
            ->paginate(min(100, (int) $request->query('per_page', 20))));
    }

    public function store(StartOpnameRequest $request): JsonResponse
    {
        $opname = $this->opnames->start((int) $request->validated('outlet_id'), $request->validated('category_id'), $request->validated('note'), $request->user());

        return (new StockOpnameResource($this->opnames->withLiveQuantities($opname)->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    public function show(Request $request, StockOpname $stockOpname): StockOpnameResource
    {
        $this->authorizeAccess($request, $stockOpname);

        return new StockOpnameResource($this->opnames->withLiveQuantities($stockOpname)->load(self::DETAIL));
    }

    public function update(SaveOpnameCountsRequest $request, StockOpname $stockOpname): StockOpnameResource
    {
        $opname = $this->opnames->saveCounts($stockOpname, $request->validated('items'), $request->validated('note'));

        return new StockOpnameResource($this->opnames->withLiveQuantities($opname)->load(self::DETAIL));
    }

    public function apply(Request $request, StockOpname $stockOpname): StockOpnameResource
    {
        $this->authorizeAccess($request, $stockOpname);

        return new StockOpnameResource($this->opnames->apply($stockOpname, $request->user())->load([...self::DETAIL, 'items']));
    }

    public function cancel(Request $request, StockOpname $stockOpname): StockOpnameResource
    {
        $this->authorizeAccess($request, $stockOpname);

        return new StockOpnameResource($this->opnames->cancel($stockOpname)->load(self::DETAIL));
    }

    private function authorizeAccess(Request $request, StockOpname $opname): void
    {
        abort_unless($request->user()->role->canManageStock() && $request->user()->canAccessOutlet($opname->outlet_id), 403);
    }
}
