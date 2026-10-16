<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\KitchenTicketStatusRequest;
use App\Http\Resources\KitchenTicketResource;
use App\Models\KitchenTicket;
use App\Services\KitchenService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Kitchen Display (3.4): tiket aktif per outlet + ubah status. */
class KitchenController extends Controller
{
    public function __construct(private readonly KitchenService $kitchen) {}

    /** Tiket baru/diproses + yang siap dalam 30 menit terakhir (belum disajikan), terlama di atas. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $outletId = (int) ($request->query('outlet_id') ?: ($request->user()->accessibleOutletIds()[0] ?? 0));
        abort_unless($request->user()->canAccessOutlet($outletId), 403);

        return KitchenTicketResource::collection(KitchenTicket::query()
            ->where('outlet_id', $outletId)
            ->where(fn ($q) => $q->whereIn('status', ['new', 'preparing'])
                ->orWhere(fn ($r) => $r->where('status', 'ready')->where('ready_at', '>=', now()->subMinutes(30))))
            ->oldest('id')->limit(200)->get());
    }

    public function status(KitchenTicketStatusRequest $request, KitchenTicket $ticket): KitchenTicketResource
    {
        return new KitchenTicketResource($this->kitchen->setStatus($ticket, $request->validated('status')));
    }
}
