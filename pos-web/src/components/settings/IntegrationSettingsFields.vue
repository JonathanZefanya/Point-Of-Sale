<script setup>
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'

/**
 * Integrasi (hanya pemilik): Midtrans untuk QRIS dinamis & SMTP untuk email laporan.
 * Server key dan password tidak pernah dikirim balik oleh server; kolom kosong = nilai tersimpan tetap dipakai.
 */
const settings = defineModel({ type: Object, required: true }) // { midtrans, smtp } dari GET /settings

defineProps({
  errors: { type: Object, default: () => ({}) },
})

const notificationUrl = `${import.meta.env.VITE_API_URL}/payments/midtrans/notification`
const dashboard = computed(() => (settings.value.midtrans.is_production ? 'dashboard.midtrans.com' : 'dashboard.sandbox.midtrans.com'))
const MODES = [
  { value: 'sandbox', label: 'Sandbox (uji coba, tanpa uang sungguhan)' },
  { value: 'production', label: 'Produksi (pembayaran sungguhan)' },
]
// Select memakai nilai teks; pengaturan menyimpan boolean
const mode = computed({
  get: () => (settings.value.midtrans.is_production ? 'production' : 'sandbox'),
  set: (v) => (settings.value.midtrans.is_production = v === 'production'),
})
const ENCRYPTION = [
  { value: 'tls', label: 'TLS (port 587)' },
  { value: 'ssl', label: 'SSL (port 465)' },
]
</script>

<template>
  <section class="grid gap-3">
    <div class="flex items-center gap-2">
      <h2 class="font-semibold">Midtrans — QRIS dinamis</h2>
      <Badge :tone="settings.midtrans.server_key_set ? 'success' : 'neutral'">{{ settings.midtrans.server_key_set ? 'Aktif' : 'Belum diatur' }}</Badge>
    </div>
    <ol class="list-decimal space-y-1 rounded border border-border bg-surface-muted py-2 pl-8 pr-3 text-sm">
      <li>Masuk ke <span class="font-mono">{{ dashboard }}</span> (daftar akun Midtrans bila belum punya).</li>
      <li>Buka <span class="font-medium">Settings → Access Keys</span>, salin <span class="font-medium">Server Key</span> ke kolom di bawah.</li>
      <li>
        Buka <span class="font-medium">Settings → Payment → Notification URL</span>, isi:
        <span class="block select-all break-all font-mono">{{ notificationUrl }}</span>
        <span class="text-muted">Alamat ini harus bisa diakses dari internet. Selama belum bisa, status lunas tetap dicek otomatis dari layar kasir.</span>
      </li>
      <li>Pastikan metode <span class="font-medium">QRIS</span> sudah aktif di akun Midtrans Anda.</li>
    </ol>
    <div class="grid gap-4 sm:grid-cols-2">
      <Select v-model="mode" label="Mode" :options="MODES" :error="errors['midtrans.is_production']" />
      <Input
        v-model="settings.midtrans.server_key"
        type="password"
        autocomplete="off"
        label="Server Key"
        :placeholder="settings.midtrans.server_key_set ? 'Tersimpan — isi untuk mengganti' : settings.midtrans.is_production ? 'Mid-server-…' : 'SB-Mid-server-…'"
        :error="errors['midtrans.server_key']"
      />
    </div>
    <Checkbox
      v-if="settings.midtrans.server_key_set"
      v-model="settings.midtrans.remove_server_key"
      label="Hapus server key tersimpan"
      description="QRIS dinamis dinonaktifkan; kasir tetap bisa memakai QRIS statis."
    />
  </section>

  <section class="grid gap-3">
    <div class="flex items-center gap-2">
      <h2 class="font-semibold">Server email (SMTP)</h2>
      <Badge :tone="settings.smtp.host ? 'success' : 'neutral'">{{ settings.smtp.host ? 'Diatur' : 'Memakai pengaturan server' }}</Badge>
    </div>
    <p class="text-sm text-muted">Dipakai untuk laporan harian & peringatan stok. Gmail: host smtp.gmail.com, TLS port 587, password = App Password (bukan password akun).</p>
    <div class="grid gap-4 sm:grid-cols-2">
      <Input v-model="settings.smtp.host" label="Host SMTP" placeholder="smtp.gmail.com" hint="Kosongkan untuk memakai pengaturan email di server." :error="errors['smtp.host']" />
      <div class="grid grid-cols-2 gap-2">
        <Input v-model.number="settings.smtp.port" type="number" label="Port" :error="errors['smtp.port']" />
        <Select v-model="settings.smtp.encryption" label="Enkripsi" :options="ENCRYPTION" :error="errors['smtp.encryption']" />
      </div>
      <Input v-model="settings.smtp.username" label="Username" autocomplete="off" :error="errors['smtp.username']" />
      <Input
        v-model="settings.smtp.password"
        type="password"
        autocomplete="new-password"
        label="Password"
        :placeholder="settings.smtp.password_set ? 'Tersimpan — isi untuk mengganti' : ''"
        :error="errors['smtp.password']"
      />
      <Input v-model="settings.smtp.from_address" type="email" label="Email pengirim" placeholder="laporan@tokoanda.id" :error="errors['smtp.from_address']" />
      <Input v-model="settings.smtp.from_name" label="Nama pengirim" placeholder="Kopi Senja" :error="errors['smtp.from_name']" />
    </div>
    <Checkbox v-if="settings.smtp.password_set" v-model="settings.smtp.remove_password" label="Hapus password SMTP tersimpan" />
  </section>
</template>
