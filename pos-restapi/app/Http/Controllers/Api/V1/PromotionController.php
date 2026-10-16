<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\PromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\PromotionService;
use App\Services\Sales\SaleQuoter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PromotionController extends Controller
{
    public function __construct(private readonly PromotionService $promotions) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('create', Product::class);

        return PromotionResource::collection(Promotion::query()->with('vouchers')->orderByDesc('is_active')->orderBy('name')->get());
    }

    public function store(PromotionRequest $request): JsonResponse
    {
        return (new PromotionResource($this->promotions->save(new Promotion, $request->validated())))->response()->setStatusCode(201);
    }

    public function update(PromotionRequest $request, Promotion $promotion): PromotionResource
    {
        return new PromotionResource($this->promotions->save($promotion, $request->validated()));
    }

    public function destroy(Promotion $promotion): Response
    {
        Gate::authorize('delete', Product::class);
        $promotion->delete(); // soft delete: transaksi lama menyimpan snapshot promo

        return response()->noContent();
    }

    /** POST /vouchers/validate { code, outlet_id } — cek kode di kasir sebelum dipakai. */
    public function validateVoucher(Request $request, SaleQuoter $quoter): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40'], 'outlet_id' => ['required', 'integer']]);
        abort_unless($request->user()->canAccessOutlet((int) $data['outlet_id']), 403);
        $voucher = $quoter->voucher($data['code'], (int) $data['outlet_id']);

        return response()->json(['data' => ['code' => $voucher->code, 'promotion' => new PromotionResource($voucher->promotion)]]);
    }
}
