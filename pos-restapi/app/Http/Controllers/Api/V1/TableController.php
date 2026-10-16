<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Table\MergeBillRequest;
use App\Http\Requests\Table\MoveBillRequest;
use App\Http\Requests\Table\TableRequest;
use App\Http\Resources\DiningTableResource;
use App\Http\Resources\SaleResource;
use App\Models\DiningTable;
use App\Models\Sale;
use App\Services\Tables\TableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Meja & bill meja (3.3). */
class TableController extends Controller
{
    public function __construct(private readonly TableService $tables) {}

    /** ?outlet_id=&active=1 — meja + bill terbuka (default outlet pertama user). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $outletId = (int) ($request->query('outlet_id') ?: ($request->user()->accessibleOutletIds()[0] ?? 0));
        abort_unless($request->user()->canAccessOutlet($outletId), 403);

        return DiningTableResource::collection($this->tables->forOutlet($outletId, $request->boolean('active')));
    }

    public function store(TableRequest $request): JsonResponse
    {
        return (new DiningTableResource($this->tables->save(new DiningTable, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(TableRequest $request, DiningTable $table): DiningTableResource
    {
        abort_unless($request->user()->canAccessOutlet($table->outlet_id), 403);

        return new DiningTableResource($this->tables->save($table, $request->validated()));
    }

    public function destroy(Request $request, DiningTable $table): Response
    {
        abort_unless($request->user()->role->canManageCatalog() && $request->user()->canAccessOutlet($table->outlet_id), 403);
        $this->tables->delete($table);

        return response()->noContent();
    }

    /** POST /sales/held/{sale}/move { table_id } */
    public function move(MoveBillRequest $request, Sale $sale): SaleResource
    {
        return new SaleResource($this->tables->move($sale, (int) $request->validated('table_id')));
    }

    /** POST /sales/held/{sale}/merge { target_sale_id } — isi bill {sale} digabung ke bill target. */
    public function merge(MergeBillRequest $request, Sale $sale): SaleResource
    {
        return new SaleResource($this->tables->merge($sale, (int) $request->validated('target_sale_id'), $request->user()));
    }
}
