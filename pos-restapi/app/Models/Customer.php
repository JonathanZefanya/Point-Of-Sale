<?php

namespace App\Models;

use App\Enums\CustomerGroup;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Pelanggan (berlaku di semua outlet). */
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'email', 'birth_date', 'group', 'points', 'note'];

    protected $attributes = ['group' => 'member', 'points' => 0];

    protected function casts(): array
    {
        return [
            'group' => CustomerGroup::class,
            'birth_date' => 'date',
            'points' => 'integer',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** 0812-3456 7890 / +62 812 3456 7890 / 62812... → 081234567890 */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '62') ? '0'.substr($digits, 2) : $digits;
    }
}
