<?php

namespace App\Http\Requests\ModifierGroup;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Tambah/ubah grup modifier beserta pilihannya. modifiers dengan id diubah, tanpa id ditambah,
 * yang tidak dikirim dihapus (riwayat penjualan aman karena nama & harga sudah di-snapshot).
 */
class ModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class); // aturan sama dengan katalog
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'min_select' => ['required', 'integer', 'min:0', 'max:20'],
            'max_select' => ['required', 'integer', 'min:1', 'max:20', 'gte:min_select'],
            'modifiers' => ['required', 'array', 'min:1', 'max:30'],
            'modifiers.*.id' => ['nullable', 'integer'],
            'modifiers.*.name' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'modifiers.*.price' => ['nullable', 'integer', 'min:0', 'max:999999999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ((int) $this->input('max_select') > count((array) $this->input('modifiers', []))) {
                $v->errors()->add('max_select', 'Maksimal pilihan tidak boleh lebih dari jumlah pilihan yang tersedia.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'max_select.gte' => 'Maksimal pilihan harus sama atau lebih besar dari minimal pilihan.',
            'modifiers.required' => 'Tambahkan minimal satu pilihan, mis. "Less sugar".',
            'modifiers.min' => 'Tambahkan minimal satu pilihan, mis. "Less sugar".',
            'modifiers.*.name.required' => 'Nama pilihan wajib diisi.',
            'modifiers.*.name.distinct' => 'Nama pilihan tidak boleh sama.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama grup', 'min_select' => 'minimal pilihan', 'max_select' => 'maksimal pilihan'];
    }
}
