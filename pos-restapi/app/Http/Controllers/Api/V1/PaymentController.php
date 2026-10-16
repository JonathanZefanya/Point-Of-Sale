<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreateQrisRequest;
use App\Http\Resources\PaymentIntentResource;
use App\Models\PaymentIntent;
use App\Services\Payments\MidtransQrisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** QRIS dinamis Midtrans (3.5). */
class PaymentController extends Controller
{
    public function __construct(private readonly MidtransQrisService $qris) {}

    /** Fitur pembayaran yang aktif di server (kasir menampilkan tombol QRIS dinamis bila aktif). */
    public function gateway(): JsonResponse
    {
        return response()->json(['data' => ['qris_dynamic' => $this->qris->enabled()]]);
    }

    public function createQris(CreateQrisRequest $request): JsonResponse
    {
        $intent = $this->qris->create($request->user(), (int) $request->validated('outlet_id'), (int) $request->validated('amount'));

        return (new PaymentIntentResource($intent))->response()->setStatusCode(201);
    }

    public function showQris(Request $request, PaymentIntent $intent): PaymentIntentResource
    {
        abort_unless($request->user()->canAccessOutlet($intent->outlet_id), 403);

        return new PaymentIntentResource($this->qris->refresh($intent));
    }

    /** Webhook Midtrans (tanpa login; diverifikasi lewat signature_key). */
    public function notification(Request $request): JsonResponse
    {
        $this->qris->notify($request->all());

        return response()->json(['message' => 'OK']);
    }
}
