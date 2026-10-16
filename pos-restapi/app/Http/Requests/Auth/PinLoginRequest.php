<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PinLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'digits:6'],
        ];
    }

    public function attributes(): array
    {
        return ['user_id' => 'kasir'];
    }
}
