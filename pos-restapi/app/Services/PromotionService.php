<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data hasil validasi PromotionRequest */
    public function save(Promotion $promotion, array $data): Promotion
    {
        return DB::transaction(function () use ($promotion, $data) {
            $before = $promotion->exists ? $promotion->only(['type', 'value', 'is_active']) : null;

            $promotion->fill([
                ...Arr::only($data, ['name', 'type', 'value', 'starts_at', 'ends_at', 'outlet_ids', 'is_active', 'requires_voucher']),
                'value' => $data['value'] ?? 0,
                'is_active' => $data['is_active'] ?? $promotion->is_active ?? true,
                'requires_voucher' => $data['requires_voucher'] ?? $promotion->requires_voucher ?? false,
                'rules_json' => array_filter($data['rules'] ?? [], fn ($v) => $v !== null && $v !== []),
            ])->save();

            // Satu kode voucher per promo: dibuat/diubah, atau dihapus bila dikosongkan (bila belum pernah dipakai)
            $voucher = $promotion->vouchers()->first();
            if (! empty($data['voucher_code'])) {
                $promotion->vouchers()->updateOrCreate(['id' => $voucher?->id], ['code' => $data['voucher_code'], 'quota' => $data['voucher_quota'] ?? null]);
            } elseif ($voucher && $voucher->used_count === 0) {
                $voucher->delete();
            }

            $this->audit->log($before ? 'promotion.updated' : 'promotion.created', $promotion, $before, $promotion->only(['type', 'value', 'is_active']));

            return $promotion->load('vouchers');
        });
    }
}
