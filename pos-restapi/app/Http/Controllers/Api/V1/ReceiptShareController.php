<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Sales\ReceiptBuilder;
use App\Services\Sales\ReceiptShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/** Kirim struk via WhatsApp (3.6): pesan + link struk online, dan struk publik bertanda tangan. */
class ReceiptShareController extends Controller
{
    public function share(Sale $sale, ReceiptShareService $share): JsonResponse
    {
        Gate::authorize('view', $sale);
        abort_if($sale->status === SaleStatus::Held, 404);

        return response()->json(['data' => $share->share($sale)]);
    }

    /**
     * Tanpa login; middleware `signed` menolak link yang diubah atau kedaluwarsa (403).
     * Tanpa route model binding: binding berjalan sebelum cek tanda tangan dan akan membocorkan id yang ada (404 vs 403).
     */
    public function show(int $sale, ReceiptBuilder $receipts): JsonResponse
    {
        $sale = Sale::query()->findOrFail($sale);
        abort_if($sale->status === SaleStatus::Held, 404);

        return response()->json(['data' => $receipts->build($sale)]);
    }
}
