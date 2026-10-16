<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'pin',
        'role',
        'commission_percent',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class);
    }

    /** @return list<int> */
    public function outletIds(): array
    {
        return $this->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Outlet yang boleh diakses: owner semua outlet, role lain yang ditugaskan.
     *
     * @return list<int>
     */
    public function accessibleOutletIds(): array
    {
        if ($this->role->seesAllOutlets()) {
            return Outlet::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $this->outletIds();
    }

    /** Membatasi query user ke yang boleh dilihat $actor: owner semua, lainnya satu outlet. */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if ($actor->role->seesAllOutlets()) {
            return;
        }

        $query->whereHas('outlets', fn (Builder $q) => $q->whereIn('outlets.id', $actor->outletIds()));
    }

    /** Owner boleh mengakses semua outlet; role lain hanya yang ditugaskan. */
    public function canAccessOutlet(int $outletId): bool
    {
        return $this->role->seesAllOutlets()
            || $this->outlets()->whereKey($outletId)->exists();
    }
}
