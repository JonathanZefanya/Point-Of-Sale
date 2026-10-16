<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CashMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\CashMovementRequest;
use App\Http\Requests\Shift\CloseShiftRequest;
use App\Http\Requests\Shift\CurrentShiftRequest;
use App\Http\Requests\Shift\OpenShiftRequest;
use App\Http\Resources\CashMovementResource;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ShiftController extends Controller
{
    public function __construct(private readonly ShiftService $shifts) {}

    /** Shift terbuka milik user di outlet ini; null bila belum buka (+ saran modal awal). */
    public function current(CurrentShiftRequest $request): JsonResponse
    {
        $outletId = (int) $request->validated('outlet_id');
        $shift = $this->shifts->current($request->user(), $outletId);

        return response()->json([
            'data' => $shift ? new ShiftResource($shift) : null,
            'suggested_opening_cash' => $shift ? null : $this->shifts->suggestedOpeningCash($outletId),
        ]);
    }

    public function open(OpenShiftRequest $request): JsonResponse
    {
        $shift = $this->shifts->open($request->user(), (int) $request->validated('outlet_id'), (int) $request->validated('opening_cash'));

        return (new ShiftResource($shift))->response()->setStatusCode(201);
    }

    /** Laporan shift (untuk cetak & cetak ulang). */
    public function show(Shift $shift): ShiftResource
    {
        Gate::authorize('view', $shift);

        return (new ShiftResource($shift->load(['outlet', 'user', 'cashMovements'])))
            ->additional(['report' => $this->shifts->report($shift)]);
    }

    public function cashMovement(CashMovementRequest $request, Shift $shift): JsonResponse
    {
        $movement = $this->shifts->addCashMovement(
            $shift,
            CashMovementType::from($request->validated('type')),
            (int) $request->validated('amount'),
            $request->validated('note'),
        );

        return (new CashMovementResource($movement))->response()->setStatusCode(201);
    }

    public function close(CloseShiftRequest $request, Shift $shift): ShiftResource
    {
        $shift = $this->shifts->close($shift, (int) $request->validated('actual_cash'), $request->validated('note'));

        return (new ShiftResource($shift))->additional(['report' => $this->shifts->report($shift)]);
    }
}
