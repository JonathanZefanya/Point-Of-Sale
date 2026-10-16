<?php

namespace App\Http\Requests\Concerns;

/**
 * outlet_id default = outlet pertama yang boleh diakses user (CLAUDE.md §7.1: default yang masuk akal),
 * dan wajib salah satu outlet yang boleh ia akses.
 */
trait ResolvesOutlet
{
    protected function defaultOutletId(): void
    {
        if (blank($this->input('outlet_id'))) {
            $first = $this->user()->accessibleOutletIds()[0] ?? null;
            if ($first !== null) {
                $this->merge(['outlet_id' => $first]);
            }
        }
    }

    /** @return array<int, mixed> */
    protected function outletRule(): array
    {
        return [
            'required',
            'integer',
            function (string $attribute, mixed $value, \Closure $fail) {
                if (! in_array((int) $value, $this->user()->accessibleOutletIds(), true)) {
                    $fail('Outlet tidak ditemukan atau bukan outlet Anda.');
                }
            },
        ];
    }
}
