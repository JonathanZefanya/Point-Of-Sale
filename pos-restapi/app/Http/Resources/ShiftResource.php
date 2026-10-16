<?php

namespace App\Http\Resources;

use App\Models\Shift;
use Illuminate\Http\Request;

/**
 * Selama shift terbuka, angka kas versi sistem (expected/selisih) TIDAK dikirim,
 * agar kasir menghitung kas fisik tanpa melihat targetnya (F6.2).
 *
 * @mixin Shift
 */
class ShiftResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->isOpen() ? 'open' : 'closed',
            'outlet' => $this->whenLoaded('outlet', fn () => ['id' => $this->outlet->id, 'code' => $this->outlet->code, 'name' => $this->outlet->name]),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'opening_cash' => $this->opening_cash,
            'cash_movements' => CashMovementResource::collection($this->whenLoaded('cashMovements')),
            $this->mergeWhen(! $this->isOpen(), fn () => [
                'expected_cash' => $this->expected_cash,
                'actual_cash' => $this->actual_cash,
                'difference' => $this->difference,
                'note' => $this->note,
            ]),
        ];
    }
}
