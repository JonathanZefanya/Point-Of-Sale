<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\BusinessException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $filters hasil validasi IndexUserRequest */
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $sort = $filters['sort'] ?? 'name';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return User::query()
            ->visibleTo($actor)
            ->with('outlets')
            ->when($filters['search'] ?? null, fn (Builder $q, string $s) => $q->where(
                fn (Builder $q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")
            ))
            ->when($filters['role'] ?? null, fn (Builder $q, string $role) => $q->where('role', $role))
            ->when($filters['outlet_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas('outlets', fn ($o) => $o->whereKey($id)))
            ->when(isset($filters['is_active']), fn (Builder $q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderBy(ltrim($sort, '-'), $direction)
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $user = User::query()->create(Arr::except($data, ['outlet_ids']));
            $user->outlets()->sync($this->outletIdsFor($actor, $user, $data['outlet_ids'] ?? []));

            $this->audit->log('user.created', $user, null, $this->snapshot($user));

            return $user->load('outlets');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, User $user, array $data): User
    {
        // Password/PIN kosong berarti "tidak diubah"
        $data = array_filter($data, fn ($v, $k) => ! (in_array($k, ['password', 'pin'], true) && blank($v)), ARRAY_FILTER_USE_BOTH);

        return DB::transaction(function () use ($actor, $user, $data) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $this->snapshot($user);

            $losesOwner = $user->role === Role::Owner && (
                (isset($data['role']) && $data['role'] !== Role::Owner->value)
                || (array_key_exists('is_active', $data) && ! $data['is_active'])
            );
            if ($losesOwner) {
                $this->ensureAnotherActiveOwner($user);
            }

            $user->fill(Arr::except($data, ['outlet_ids']))->save();

            if (array_key_exists('outlet_ids', $data)) {
                $user->outlets()->sync($this->outletIdsFor($actor, $user, $data['outlet_ids']));
            }

            if (! $user->is_active) {
                $user->tokens()->delete();
            }

            $after = $this->snapshot($user->load('outlets'));
            $after['password_changed'] = isset($data['password']);
            $after['pin_changed'] = isset($data['pin']);
            $this->audit->log('user.updated', $user, $before, $after);

            return $user;
        });
    }

    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->role === Role::Owner) {
                $this->ensureAnotherActiveOwner($user);
            }

            $before = $this->snapshot($user);
            $user->tokens()->delete();
            $user->delete();

            $this->audit->log('user.deleted', $user, $before);
        });
    }

    /**
     * Manager hanya mengatur outlet miliknya; outlet lain yang sudah dimiliki karyawan
     * (di luar wewenang manager) dipertahankan agar tidak terhapus diam-diam.
     *
     * @param  list<int>  $requested
     * @return list<int>
     */
    private function outletIdsFor(User $actor, User $user, array $requested): array
    {
        if ($actor->role->seesAllOutlets() || ! $user->exists) {
            return array_values(array_unique($requested));
        }

        $outsideActorScope = array_diff($user->outletIds(), $actor->outletIds());

        return array_values(array_unique([...$requested, ...$outsideActorScope]));
    }

    private function ensureAnotherActiveOwner(User $user): void
    {
        $others = User::query()
            ->where('role', Role::Owner)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->lockForUpdate()
            ->count();

        if ($others === 0) {
            throw new BusinessException('Ini satu-satunya akun Pemilik yang aktif. Tambahkan pemilik lain terlebih dahulu.', 409);
        }
    }

    /** Data aman untuk audit log (tanpa password/PIN). */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
            'outlet_ids' => $user->outletIds(),
        ];
    }
}
