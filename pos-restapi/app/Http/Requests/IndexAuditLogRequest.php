<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

/** Filter audit log (2.12): user, aksi, rentang tanggal. Hanya pemilik (log mencakup semua outlet). */
class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === Role::Owner;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['from' => 'tanggal mulai', 'to' => 'tanggal akhir'];
    }
}
