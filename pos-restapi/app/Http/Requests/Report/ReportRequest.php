<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter laporan (F11): default hari ini + outlet sendiri. Rentang maksimal 366 hari.
 * ?export=xlsx untuk unduh Excel.
 */
class ReportRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return $this->user()->role->canViewReports();
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->outletRule(),
            'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', function (string $attr, mixed $value, \Closure $fail) {
                if (strtotime($value) - strtotime((string) $this->input('from')) > 366 * 86400) {
                    $fail('Rentang laporan maksimal 1 tahun. Persempit periodenya.');
                }
            }],
            'export' => ['nullable', 'in:xlsx'],
        ];
    }

    public function attributes(): array
    {
        return ['from' => 'tanggal mulai', 'to' => 'tanggal akhir', 'date' => 'tanggal'];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
        $today = now()->toDateString();
        $this->merge([
            'date' => $this->input('date') ?: $today,
            'from' => $this->input('from') ?: $today,
            'to' => $this->input('to') ?: $this->input('from') ?: $today,
        ]);
    }
}
