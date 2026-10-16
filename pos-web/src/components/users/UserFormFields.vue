<script setup>
import { computed, watch } from 'vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import { ROLE } from '@/lib/roles'

/**
 * Field form karyawan. Validasi saat blur ada di sini; validasi server (422)
 * masuk lewat prop `errors` dari halaman.
 */
const form = defineModel({ type: Object, required: true })
const errors = defineModel('errors', { type: Object, required: true })

const props = defineProps({
  roles: { type: Array, default: () => [] }, // dari GET /roles
  outlets: { type: Array, default: () => [] }, // dari GET /outlets
  isEdit: { type: Boolean, default: false },
  isSelf: { type: Boolean, default: false },
  hasPin: { type: Boolean, default: false }, // PIN sudah tersimpan (mode ubah)
})

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

const roleOptions = computed(() =>
  props.roles.filter((r) => r.assignable || r.value === form.value.role).map((r) => ({ value: r.value, label: r.label })),
)
const isOwnerRole = computed(() => form.value.role === ROLE.OWNER)
const pinRequired = computed(() => form.value.role === ROLE.CASHIER && !props.hasPin)

const rules = {
  name: (v) => (v.trim() ? '' : 'Nama wajib diisi.'),
  email: (v) => {
    if (!v.trim()) return 'Email wajib diisi.'
    return EMAIL_PATTERN.test(v) ? '' : 'Format email salah, contoh: nama@toko.com'
  },
  password: (v) => {
    if (!v) return props.isEdit ? '' : 'Password wajib diisi.'
    return v.length < 8 ? 'Password minimal 8 karakter.' : ''
  },
  pin: (v) => {
    if (!v) return pinRequired.value ? 'PIN wajib diisi untuk kasir agar bisa login di perangkat kasir.' : ''
    return /^\d{6}$/.test(v) ? '' : 'PIN harus 6 digit angka.'
  },
}

function check(field) {
  errors.value = { ...errors.value, [field]: rules[field](form.value[field] ?? '') }
}

function clearError(field) {
  if (errors.value[field]) errors.value = { ...errors.value, [field]: '' }
}

// Ganti peran → syarat PIN ikut berubah; tampilkan/hilangkan errornya langsung bila sudah pernah dicek
watch(
  () => form.value.role,
  () => {
    if (errors.value.pin !== undefined) check('pin')
  },
)

/** Dipanggil halaman sebelum submit. */
function validateAll() {
  const next = Object.fromEntries(Object.keys(rules).map((f) => [f, rules[f](form.value[f] ?? '')]))
  next.outlet_ids = !isOwnerRole.value && !form.value.outlet_ids.length ? 'Pilih minimal satu outlet tempat karyawan bekerja.' : ''
  errors.value = next
  return Object.values(next).every((e) => !e)
}

defineExpose({ validateAll })
</script>

<template>
  <div class="grid gap-5">
    <section class="grid gap-4 sm:grid-cols-2">
      <Input v-model="form.name" label="Nama" required autocomplete="off" :error="errors.name" @blur="check('name')" @input="clearError('name')" />
      <Input
        v-model="form.email"
        label="Email"
        type="email"
        required
        autocomplete="off"
        hint="Dipakai untuk login back office."
        :error="errors.email"
        @blur="check('email')"
        @input="clearError('email')"
      />
      <Select
        v-model="form.role"
        label="Peran"
        required
        :options="roleOptions"
        :disabled="isSelf"
        :hint="isSelf ? 'Peran akun sendiri tidak bisa diubah.' : ''"
        :error="errors.role"
      />
    </section>

    <section class="grid gap-4 sm:grid-cols-2">
      <Input
        v-model="form.password"
        label="Password"
        type="password"
        autocomplete="new-password"
        :required="!isEdit"
        :hint="isEdit ? 'Kosongkan jika tidak diubah. Minimal 8 karakter.' : 'Minimal 8 karakter.'"
        :error="errors.password"
        @blur="check('password')"
        @input="clearError('password')"
      />
      <Input
        v-model="form.pin"
        label="PIN kasir (6 digit)"
        type="password"
        inputmode="numeric"
        maxlength="6"
        autocomplete="off"
        :required="pinRequired"
        :hint="hasPin ? 'PIN sudah diatur. Kosongkan jika tidak diubah.' : 'Untuk login di layar kasir dan persetujuan supervisor.'"
        :error="errors.pin"
        @blur="check('pin')"
        @input="clearError('pin')"
      />
    </section>

    <fieldset class="grid gap-2">
      <legend class="mb-1.5 font-medium">Outlet tempat bekerja<span v-if="!isOwnerRole" class="text-danger" aria-hidden="true"> *</span></legend>
      <p v-if="isOwnerRole" class="text-muted">Pemilik otomatis bisa mengakses semua outlet.</p>
      <template v-else>
        <p v-if="!outlets.length" class="text-muted">Belum ada outlet yang bisa Anda pilih.</p>
        <div class="grid gap-2 sm:grid-cols-2">
          <Checkbox
            v-for="outlet in outlets"
            :key="outlet.id"
            v-model="form.outlet_ids"
            :value="outlet.id"
            :label="`${outlet.code} · ${outlet.name}`"
            @change="clearError('outlet_ids')"
          />
        </div>
        <p v-if="errors.outlet_ids" class="text-danger" role="alert">{{ errors.outlet_ids }}</p>
      </template>
    </fieldset>

    <Input
      v-model.number="form.commission_percent"
      type="number"
      min="0"
      max="100"
      step="0.01"
      label="Komisi penjualan (%)"
      hint="Dihitung dari penjualan bersih yang dicatat karyawan ini (laporan Komisi). Isi 0 bila tanpa komisi."
      class="sm:max-w-xs"
      :error="errors.commission_percent"
    />

    <fieldset v-if="isEdit" class="grid gap-2">
      <legend class="mb-1.5 font-medium">Status</legend>
      <Checkbox
        v-model="form.is_active"
        label="Akun aktif"
        :disabled="isSelf"
        :description="isSelf ? 'Anda tidak bisa menonaktifkan akun sendiri.' : 'Nonaktifkan jika karyawan cuti atau berhenti. Data transaksinya tetap tersimpan.'"
      />
      <p v-if="errors.is_active" class="text-danger" role="alert">{{ errors.is_active }}</p>
    </fieldset>
  </div>
</template>
