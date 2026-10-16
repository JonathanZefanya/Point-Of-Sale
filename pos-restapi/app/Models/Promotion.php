<?php

namespace App\Models;

use App\Enums\PromotionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Promo (2.5). rules_json:
 * - item_*: { product_ids: [], category_ids: [] } — keduanya kosong = semua produk
 * - order_*: { min_purchase: int, max_discount: ?int }
 * - buy_x_get_y: { buy_product_id, buy_quantity, get_product_id, get_quantity }
 * - semua tipe (happy hour): { days: [1..7] (Senin=1), start_time: "15:00", end_time: "17:00" }
 */
class Promotion extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'value', 'rules_json', 'starts_at', 'ends_at', 'outlet_ids', 'requires_voucher', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'integer',
            'rules_json' => 'array',
            'outlet_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'requires_voucher' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function rule(string $key, mixed $default = null): mixed
    {
        return $this->rules_json[$key] ?? $default;
    }

    /** Promo aktif, dalam periode, di outlet ini (jadwal happy hour dicek terpisah). */
    public function scopeRunning(Builder $query, CarbonInterface $at): void
    {
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at));
    }

    /** Berlaku di outlet & jam ini (happy hour). */
    public function appliesAt(int $outletId, CarbonInterface $at): bool
    {
        if ($this->outlet_ids && ! in_array($outletId, array_map('intval', $this->outlet_ids), true)) {
            return false;
        }
        $days = $this->rule('days');
        if ($days && ! in_array($at->dayOfWeekIso, array_map('intval', $days), true)) {
            return false;
        }
        $start = $this->rule('start_time');
        $end = $this->rule('end_time');
        $time = $at->format('H:i');

        return (! $start || $time >= $start) && (! $end || $time < $end);
    }
}
