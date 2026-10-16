<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/** Rekap absensi (3.7): pemilik & manajer. Default 7 hari terakhir. */
class IndexAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role->canManageCatalog();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: now()->subDays(6)->toDateString(),
            'to' => $this->input('to') ?: now()->toDateString(),
        ]);
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
