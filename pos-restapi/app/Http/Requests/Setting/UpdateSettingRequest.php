<?php

namespace App\Http\Requests\Setting;

use App\Enums\Role;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PUT /settings. Pengaturan global (bisnis, pajak, pembulatan, struk) hanya Pemilik;
 * bagian `outlet` (tarif & teks struk per outlet) juga boleh Manajer outlet tersebut.
 */
class UpdateSettingRequest extends FormRequest
{
    public const GLOBAL_SECTIONS = ['business', 'tax', 'service_charge', 'rounding', 'receipt', 'discount', 'loyalty', 'kitchen', 'notifications', 'midtrans', 'smtp'];

    public function authorize(): bool
    {
        return in_array($this->user()->role, [Role::Owner, Role::Manager], true);
    }

    public function rules(): array
    {
        return [
            'business.name' => ['sometimes', 'required', 'string', 'max:100'],
            'business.address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business.phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'business.npwp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'tax.enabled' => ['sometimes', 'boolean'],
            'tax.inclusive' => ['sometimes', 'boolean'],
            'service_charge.enabled' => ['sometimes', 'boolean'],
            'rounding.enabled' => ['sometimes', 'boolean'],
            'rounding.to' => ['sometimes', 'integer', Rule::in([1, 100, 500, 1000])],
            'rounding.mode' => ['sometimes', Rule::in(['nearest', 'up', 'down'])],
            'receipt.paper_width' => ['sometimes', 'integer', Rule::in([58, 80])],
            'receipt.auto_print' => ['sometimes', 'boolean'],
            'receipt.show_cashier' => ['sometimes', 'boolean'],
            'discount.manual_limit_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'loyalty.enabled' => ['sometimes', 'boolean'],
            'loyalty.earn_per' => ['sometimes', 'integer', 'min:100', 'max:100000000'],
            'loyalty.point_value' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'kitchen.enabled' => ['sometimes', 'boolean'],
            'notifications.recipients' => ['sometimes', 'nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                foreach (preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail("Alamat email {$email} tidak valid. Pisahkan beberapa email dengan koma.");
                    }
                }
            }],
            // Integrasi: rahasia kosong = tidak diubah; remove_* = hapus
            'midtrans.is_production' => ['sometimes', 'boolean'],
            'midtrans.server_key' => ['sometimes', 'nullable', 'string', 'max:200', function ($attr, $value, $fail) {
                $production = $this->has('midtrans.is_production') ? $this->boolean('midtrans.is_production') : (bool) Setting::get('midtrans.is_production', false);
                if (filled($value) && ! str_starts_with($value, $production ? 'Mid-server-' : 'SB-Mid-server-')) {
                    $fail($production
                        ? 'Server key produksi diawali "Mid-server-". Salin dari dashboard.midtrans.com → Settings → Access Keys.'
                        : 'Server key sandbox diawali "SB-Mid-server-". Salin dari dashboard.sandbox.midtrans.com → Settings → Access Keys.');
                }
            }],
            'midtrans.remove_server_key' => ['sometimes', 'boolean'],
            'smtp.host' => ['sometimes', 'nullable', 'string', 'max:200'],
            'smtp.port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'smtp.username' => ['sometimes', 'nullable', 'string', 'max:200'],
            'smtp.password' => ['sometimes', 'nullable', 'string', 'max:200'],
            'smtp.remove_password' => ['sometimes', 'boolean'],
            'smtp.encryption' => ['sometimes', 'nullable', Rule::in(['tls', 'ssl'])],
            'smtp.from_address' => ['sometimes', 'nullable', 'email', 'max:200', 'required_with:smtp.host'],
            'smtp.from_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notifications.daily_report' => ['sometimes', 'boolean'],
            'notifications.low_stock' => ['sometimes', 'boolean'],

            'outlet' => ['sometimes', 'array'],
            'outlet.id' => ['required_with:outlet', 'integer'],
            'outlet.tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'outlet.service_charge_rate' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'outlet.receipt_header' => ['sometimes', 'nullable', 'string', 'max:500'],
            'outlet.receipt_footer' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $user = $this->user();

            if ($user->role !== Role::Owner && $this->hasAny(self::GLOBAL_SECTIONS)) {
                $v->errors()->add('business', 'Hanya pemilik yang bisa mengubah pengaturan bisnis, pajak, pembulatan, dan struk.');
            }
            if ($this->has('outlet.id') && ! $user->canAccessOutlet((int) $this->input('outlet.id'))) {
                $v->errors()->add('outlet.id', 'Outlet tidak ditemukan atau bukan outlet Anda.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'business.name' => 'nama bisnis',
            'rounding.to' => 'kelipatan pembulatan',
            'receipt.paper_width' => 'lebar kertas',
            'discount.manual_limit_percent' => 'batas diskon manual',
            'loyalty.earn_per' => 'belanja per 1 poin',
            'loyalty.point_value' => 'nilai 1 poin',
            'outlet.tax_rate' => 'tarif pajak',
            'outlet.service_charge_rate' => 'tarif service charge',
            'outlet.receipt_header' => 'header struk',
            'outlet.receipt_footer' => 'footer struk',
        ];
    }
}
