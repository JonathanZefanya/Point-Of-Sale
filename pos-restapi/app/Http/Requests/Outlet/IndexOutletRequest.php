<?php

namespace App\Http\Requests\Outlet;

use Illuminate\Foundation\Http\FormRequest;

class IndexOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // hasil sudah dibatasi Outlet::visibleTo()
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'all' => ['nullable', 'boolean'], // semua outlet (tujuan transfer stok), untuk role pengelola stok
        ];
    }
}
