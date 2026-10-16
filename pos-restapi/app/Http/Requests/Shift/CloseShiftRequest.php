<?php

namespace App\Http\Requests\Shift;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('manage', $this->route('shift'));
    }

    public function rules(): array
    {
        return [
            'actual_cash' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:500'], // wajib bila ada selisih — dicek di ShiftService
        ];
    }

    public function attributes(): array
    {
        return ['actual_cash' => 'kas fisik', 'note' => 'catatan'];
    }
}
