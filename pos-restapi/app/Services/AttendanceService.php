<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Absen masuk/pulang (3.7). Satu absensi terbuka per karyawan. */
class AttendanceService
{
    public function current(User $user): ?Attendance
    {
        return Attendance::query()->open()->where('user_id', $user->id)->with('outlet')->latest('id')->first();
    }

    public function clockIn(User $user, int $outletId, ?string $note = null): Attendance
    {
        return DB::transaction(function () use ($user, $outletId, $note) {
            User::query()->whereKey($user->id)->lockForUpdate()->first(); // cegah absen masuk dobel
            if ($open = $this->current($user)) {
                throw new BusinessException("Anda sudah absen masuk sejak {$open->clock_in_at->format('d/m H:i')} di {$open->outlet->name}. Absen pulang dulu.");
            }

            return Attendance::query()->create(['user_id' => $user->id, 'outlet_id' => $outletId, 'clock_in_at' => now(), 'note' => $note])->load('outlet');
        });
    }

    /** Dipanggil saat membuka shift: absen masuk otomatis bila belum. */
    public function ensureClockedIn(User $user, int $outletId): void
    {
        if (! $this->current($user)) {
            $this->clockIn($user, $outletId);
        }
    }

    /**
     * Rekap absensi dalam periode (outlet yang boleh diakses): daftar berpaginasi + total per karyawan.
     *
     * @param  array<string, mixed>  $f  hasil validasi IndexAttendanceRequest
     */
    public function report(User $actor, array $f): array
    {
        $outlets = $actor->accessibleOutletIds();
        $query = Attendance::query()
            ->whereIn('outlet_id', isset($f['outlet_id']) ? array_intersect([(int) $f['outlet_id']], $outlets) : $outlets)
            ->when($f['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->whereBetween('clock_in_at', ["{$f['from']} 00:00:00", "{$f['to']} 23:59:59"]);

        $totals = (clone $query)->with('user')->get()->groupBy('user_id')->map(fn ($rows) => [
            'user' => ['id' => $rows[0]->user->id, 'name' => $rows[0]->user->name],
            'days' => $rows->map(fn ($a) => $a->clock_in_at->toDateString())->unique()->count(),
            'minutes' => $rows->sum(fn ($a) => $a->minutes()),
        ])->sortBy('user.name')->values();

        return [$query->with(['user', 'outlet'])->latest('clock_in_at')->paginate($f['per_page'] ?? 50), $totals];
    }

    public function clockOut(User $user, ?string $note = null): Attendance
    {
        return DB::transaction(function () use ($user, $note) {
            $open = Attendance::query()->open()->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $open) {
                throw new BusinessException('Anda belum absen masuk.');
            }
            $open->update(['clock_out_at' => now(), 'note' => $note ?? $open->note]);

            return $open->load('outlet');
        });
    }
}
