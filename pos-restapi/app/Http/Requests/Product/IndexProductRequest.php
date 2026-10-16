<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public const SORTABLE = ['name', 'sku', 'sell_price', 'created_at'];

    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Product::class);
    }

    public function rules(): array
    {
        $sorts = collect(self::SORTABLE)->flatMap(fn ($f) => [$f, "-{$f}"])->all();

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'is_active' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in($sorts)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'], // layar kasir memuat banyak sekaligus
            // Kasir: harga & ketersediaan outlet ini (2.8); outlet yang tidak boleh diakses diabaikan
            'outlet_id' => ['nullable', 'integer'],
            'available_only' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['sort.in' => 'Urutan tidak dikenal. Gunakan salah satu: '.implode(', ', self::SORTABLE).' (awali "-" untuk menurun).'];
    }
}
