<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PinUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outlet_code' => ['required', 'string', 'max:10'],
        ];
    }

    public function attributes(): array
    {
        return ['outlet_code' => 'kode outlet'];
    }
}
