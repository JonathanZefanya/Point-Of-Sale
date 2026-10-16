<?php

namespace App\Services;

use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Models\Outlet;
use App\Models\Setting;
use App\Support\Integrations;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Pengaturan aplikasi dalam bentuk bertingkat: "tax.inclusive" → ['tax' => ['inclusive' => ...]]. */
class SettingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Pengaturan untuk GET /settings. Nilai rahasia (server key, password SMTP) tidak pernah dikirim;
     * diganti penanda `{nama}_set` (true bila sudah diisi).
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $values = $this->flat();
        foreach (Integrations::SECRET_KEYS as $key) {
            $values["{$key}_set"] = Integrations::secret($key) !== null;
            unset($values[$key]);
        }

        return Arr::undot($values);
    }

    /**
     * Simpan pengaturan global + per outlet. Hanya nilai yang berubah yang dicatat ke audit log.
     *
     * @param  array<string, mixed>  $data  hasil validasi UpdateSettingRequest
     */
    public function update(array $data): void
    {
        DB::transaction(function () use ($data) {
            $current = $this->flat();
            $incoming = Arr::dot(Arr::only($data, UpdateSettingRequest::GLOBAL_SECTIONS));
            $before = [];
            $after = [];

            foreach ($incoming as $key => $value) {
                if ($this->storeSecret($key, $value, $before, $after)) {
                    continue;
                }
                if (($current[$key] ?? null) !== $value) {
                    $before[$key] = $current[$key] ?? null;
                    $after[$key] = $value;
                    Setting::put($key, $value);
                }
            }

            if (isset($data['outlet'])) {
                $outlet = Outlet::query()->lockForUpdate()->findOrFail($data['outlet']['id']);
                $fields = Arr::except($data['outlet'], ['id']);
                foreach ($fields as $key => $value) {
                    if ((string) $outlet->{$key} !== (string) $value) {
                        $before["outlet.{$outlet->id}.{$key}"] = $outlet->{$key};
                        $after["outlet.{$outlet->id}.{$key}"] = $value;
                    }
                }
                $outlet->update($fields);
            }

            if ($after) {
                $this->audit->log('settings.updated', null, $before, $after);
            }
        });
    }

    /**
     * Key rahasia & penanda hapusnya: kosong = tidak diubah, `remove_*` = hapus, selain itu dienkripsi.
     * Audit log hanya mencatat bahwa nilainya diubah, tidak pernah isinya.
     *
     * @return bool true bila $key sudah ditangani di sini
     */
    private function storeSecret(string $key, mixed $value, array &$before, array &$after): bool
    {
        $removeFor = ['midtrans.remove_server_key' => 'midtrans.server_key', 'smtp.remove_password' => 'smtp.password'];
        if (isset($removeFor[$key])) {
            $secret = $removeFor[$key];
            if ($value && Integrations::secret($secret) !== null) {
                Setting::query()->whereKey($secret)->delete();
                [$before[$secret], $after[$secret]] = ['(tersimpan)', '(dihapus)'];
            }

            return true;
        }
        if (! in_array($key, Integrations::SECRET_KEYS, true)) {
            return false;
        }
        if (filled($value)) {
            $before[$key] = Integrations::secret($key) !== null ? '(tersimpan)' : null;
            $after[$key] = '(diubah)';
            Setting::put($key, Crypt::encryptString((string) $value));
        }

        return true;
    }

    /** @return array<string, mixed> key bertitik → nilai, default diisi dari SettingSeeder */
    private function flat(): array
    {
        $values = SettingSeeder::DEFAULTS;
        foreach (Setting::query()->get(['key', 'value']) as $setting) {
            $values[$setting->key] = $setting->value;
        }

        return $values;
    }
}
