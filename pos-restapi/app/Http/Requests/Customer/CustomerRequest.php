<?php

namespace App\Http\Requests\Customer;

use App\Enums\CustomerGroup;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah (tanpa route {customer}) & ubah pelanggan. */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer ? $this->user()->can('update', $customer) : $this->user()->can('create', Customer::class);
    }

    public function rules(): array
    {
        $required = $this->route('customer') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:100'],
            'phone' => [
                'nullable', 'string', 'regex:/^0\d{8,14}$/',
                Rule::unique('customers', 'phone')->ignore($this->route('customer'))->whereNull('deleted_at'),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'group' => ['sometimes', Rule::enum(CustomerGroup::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor HP tidak valid. Contoh: 081234567890.',
            'phone.unique' => 'Nomor HP ini sudah terdaftar atas pelanggan lain. Cari pelanggan dengan nomor ini.',
            'birth_date.before' => 'Tanggal lahir harus sebelum hari ini.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama pelanggan', 'phone' => 'nomor HP', 'birth_date' => 'tanggal lahir', 'group' => 'grup'];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Customer::normalizePhone($this->input('phone'))]);
        }
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }
}
