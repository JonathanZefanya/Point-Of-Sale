<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;

/** Absen masuk/pulang diri sendiri (3.7). outlet_id hanya untuk absen masuk. */
class ClockRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return true; // setiap karyawan aktif mencatat absensinya sendiri
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('*clock-in')) {
            $this->defaultOutletId();
        }
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->routeIs('*clock-in') ? $this->outletRule() : ['prohibited'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
