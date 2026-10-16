<?php

namespace App\Http\Requests\Shift;

use App\Enums\CashMovementType;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CashMovementRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('manage', $this->route('shift'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CashMovementType::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'note' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return ['note.required' => 'Tulis keterangan, mis. "Beli es batu" atau "Tambah uang kembalian".'];
    }

    public function attributes(): array
    {
        return ['type' => 'jenis', 'amount' => 'nominal', 'note' => 'keterangan'];
    }
}
