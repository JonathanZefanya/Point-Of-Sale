<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use Illuminate\Http\Request;

/** @mixin Attendance */
class AttendanceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'outlet' => $this->whenLoaded('outlet', fn () => ['id' => $this->outlet->id, 'code' => $this->outlet->code, 'name' => $this->outlet->name]),
            'clock_in_at' => $this->clock_in_at,
            'clock_out_at' => $this->clock_out_at,
            'minutes' => $this->minutes(),
            'note' => $this->note,
        ];
    }
}
