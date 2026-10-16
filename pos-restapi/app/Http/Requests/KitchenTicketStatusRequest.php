<?php

namespace App\Http\Requests;

use App\Models\KitchenTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Ubah status tiket dapur (3.4). Staf outlet tiket tersebut. */
class KitchenTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->canAccessOutlet($this->route('ticket')->outlet_id);
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(array_keys(KitchenTicket::STATUSES))]];
    }
}
