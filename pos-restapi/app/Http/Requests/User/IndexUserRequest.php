<?php

namespace App\Http\Requests\User;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends FormRequest
{
    public const SORTABLE = ['name', 'email', 'role', 'created_at'];

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', User::class);
    }

    public function rules(): array
    {
        $sorts = collect(self::SORTABLE)->flatMap(fn ($f) => [$f, "-{$f}"])->all();

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'outlet_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in($sorts)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['sort.in' => 'Urutan tidak dikenal. Gunakan salah satu: '.implode(', ', self::SORTABLE).' (awali "-" untuk menurun).'];
    }
}
