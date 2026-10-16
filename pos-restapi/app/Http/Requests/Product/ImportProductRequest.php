<?php

namespace App\Http\Requests\Product;

use App\Http\Requests\Concerns\ResolvesOutlet;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/** Import produk (2.10): preview=1 hanya memeriksa; tanpa preview menyimpan semua baris. */
class ImportProductRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
            'preview' => ['sometimes', 'boolean'],
            'outlet_id' => $this->outletRule(), // tujuan stok awal produk baru
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file Excel (.xlsx) atau CSV.',
            'file.mimes' => 'File harus Excel (.xlsx) atau CSV. Unduh template bila ragu.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }
}
