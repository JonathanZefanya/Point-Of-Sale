<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Absensi karyawan (3.7): satu baris per masuk–pulang. clock_out_at null = masih bekerja. */
class Attendance extends Model
{
    protected $fillable = ['user_id', 'outlet_id', 'clock_in_at', 'clock_out_at', 'note'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'outlet_id' => 'integer', 'clock_in_at' => 'datetime', 'clock_out_at' => 'datetime'];
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('clock_out_at');
    }

    /** Durasi kerja dalam menit (sampai sekarang bila belum pulang). */
    public function minutes(): int
    {
        return (int) $this->clock_in_at->diffInMinutes($this->clock_out_at ?? now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
