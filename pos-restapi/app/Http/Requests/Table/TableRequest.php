<?php

namespace App\Http\Requests\Table;

use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah/ubah meja (3.3). Pemilik & manajer. Nama meja unik per outlet. */
class TableRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return $this->user()->role->canManageCatalog();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->route('table')) {
            $this->defaultOutletId();
        }
    }

    public function rules(): array
    {
        $table = $this->route('table');
        $outletId = $table?->outlet_id ?? (int) $this->input('outlet_id');

        return [
            'outlet_id' => $table ? ['prohibited'] : $this->outletRule(),
            'name' => ['required', 'string', 'max:30', Rule::unique('dining_tables', 'name')->where('outlet_id', $outletId)->whereNull('deleted_at')->ignore($table?->id)],
            'area' => ['nullable', 'string', 'max:40'],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Nama meja ini sudah ada di outlet ini. Pakai nama lain, mis. "Meja 12".',
            'outlet_id.prohibited' => 'Outlet meja tidak bisa diubah. Hapus lalu buat meja baru di outlet lain.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama meja', 'capacity' => 'kapasitas'];
    }
}
