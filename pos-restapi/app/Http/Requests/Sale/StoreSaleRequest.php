<?php

namespace App\Http\Requests\Sale;

use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Http\Requests\Concerns\ResolvesOutlet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /sales — keranjang + pembayaran. Harga TIDAK diterima dari klien:
 * server menghitung ulang dari data produk (CLAUDE.md §6).
 */
class StoreSaleRequest extends FormRequest
{
    use ResolvesOutlet;

    public function authorize(): bool
    {
        return in_array($this->user()->role, [Role::Owner, Role::Manager, Role::Cashier], true);
    }

    public function rules(): array
    {
        return [
            'outlet_id' => $this->outletRule(),
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.modifier_ids' => ['nullable', 'array', 'max:30'],
            'items.*.modifier_ids.*' => ['integer'],
            'payments' => ['required', 'array', 'min:1', 'max:10'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'payments.*.intent_id' => ['nullable', 'integer'], // QRIS dinamis yang sudah lunas (3.5)
            'note' => ['nullable', 'string', 'max:500'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'held_sale_id' => ['nullable', 'integer'], // melanjutkan transaksi tersimpan (F4)
            'order_type' => ['nullable', Rule::enum(OrderType::class)], // F&B (3.3)
            ...self::discountRules(),
            'supervisor_pin' => ['nullable', 'string', 'digits:6'],
        ];
    }

    /** Voucher & diskon manual (2.5) — dipakai juga oleh QuoteSaleRequest. */
    public static function discountRules(): array
    {
        return [
            'voucher_code' => ['nullable', 'string', 'max:40'],
            'manual_discount' => ['nullable', 'array'],
            'manual_discount.type' => ['required_with:manual_discount', Rule::in(['percent', 'amount'])],
            'manual_discount.value' => ['required_with:manual_discount', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Keranjang masih kosong. Tambahkan produk terlebih dahulu.',
            'items.min' => 'Keranjang masih kosong. Tambahkan produk terlebih dahulu.',
            'payments.required' => 'Pilih metode pembayaran.',
            'payments.min' => 'Pilih metode pembayaran.',
            'items.*.quantity.min' => 'Jumlah minimal 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.quantity' => 'jumlah',
            'payments.*.method' => 'metode bayar',
            'payments.*.amount' => 'nominal bayar',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->defaultOutletId();

        // Idempotency key boleh dari header (standar) atau body
        if (! $this->filled('idempotency_key') && $this->hasHeader('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }
}
