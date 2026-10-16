<?php

namespace App\Http\Requests\Sale;

use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /sales/sync — antrian transaksi offline (BE-3.2). Hanya amplop yang divalidasi di sini;
 * tiap transaksi divalidasi terpisah (saleRules) agar satu data rusak tidak menahan seluruh antrian.
 */
class SyncSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, [Role::Owner, Role::Manager, Role::Cashier], true);
    }

    public function rules(): array
    {
        return [
            'sales' => ['required', 'array', 'min:1', 'max:100'],
            'sales.*' => ['array'],
            'sales.*.client_id' => ['required', 'string', 'max:64', 'distinct'],
        ];
    }

    /** Aturan satu transaksi offline. Harga dari perangkat (unit_price); poin tidak bisa dipakai offline. */
    public static function saleRules(User $user): array
    {
        return [
            'client_id' => ['required', 'string', 'max:64'],
            'outlet_id' => ['required', 'integer', function ($attr, $value, $fail) use ($user) {
                if (! $user->canAccessOutlet((int) $value)) {
                    $fail('Outlet transaksi ini bukan outlet kasir.');
                }
            }],
            'shift_id' => ['required', 'integer'],
            'completed_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes(10)->toIso8601String(), 'after:'.now()->subDays(30)->toIso8601String()],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.modifier_ids' => ['nullable', 'array', 'max:30'],
            'items.*.modifier_ids.*' => ['integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
            'payments' => ['required', 'array', 'min:1', 'max:10'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Points])],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'grand_total' => ['nullable', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
