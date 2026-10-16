<?php

namespace App\Http\Requests\Category;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Tambah (tanpa route {category}) & ubah kategori. */
class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category
            ? $this->user()->can('update', $category)
            : $this->user()->can('create', Category::class);
    }

    public function rules(): array
    {
        $required = $this->route('category') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:60', Rule::unique('categories', 'name')->ignore($this->route('category'))],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Kategori dengan nama ini sudah ada.'];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }
}
