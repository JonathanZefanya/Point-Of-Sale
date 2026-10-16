<script setup>
import { ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Textarea from '@/components/ui/Textarea.vue'
import http from '@/lib/http'
import { useToastStore } from '@/stores/toast'

/**
 * Notifikasi email (3.8): penerima, laporan harian (07:00, data kemarin), peringatan stok menipis (08:00).
 * "Kirim Contoh" memakai pengaturan yang sudah tersimpan.
 */
const notifications = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
  dirty: { type: Boolean, default: false }, // ada perubahan yang belum disimpan
})

const toast = useToastStore()
const sending = ref(false)

async function sendTest() {
  sending.value = true
  try {
    const { data } = await http.post('/settings/test-email')
    toast.success(`Contoh laporan dikirim ke ${data.data.recipients.join(', ')}.`)
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <section class="grid gap-3">
    <h2 class="font-semibold">Notifikasi email</h2>
    <Textarea
      v-model="notifications.recipients"
      label="Email penerima"
      :rows="2"
      maxlength="500"
      placeholder="owner@toko.id, manajer@toko.id"
      hint="Pisahkan beberapa email dengan koma."
      :disabled="disabled"
      :error="errors['notifications.recipients']"
    />
    <Checkbox v-model="notifications.daily_report" :disabled="disabled" label="Laporan penjualan harian" description="Dikirim setiap pukul 07:00 berisi ringkasan kemarin per outlet: omzet, transaksi, metode bayar, produk terlaris, stok menipis." />
    <Checkbox v-model="notifications.low_stock" :disabled="disabled" label="Peringatan stok menipis" description="Dikirim setiap pukul 08:00 bila ada produk habis atau di bawah stok minimum." />
    <div class="flex flex-wrap items-center gap-2">
      <Button :disabled="disabled || dirty" :loading="sending" loading-text="Mengirim..." @click="sendTest">Kirim Contoh Sekarang</Button>
      <span v-if="dirty" class="text-sm text-muted">Simpan pengaturan dulu sebelum mengirim contoh.</span>
    </div>
  </section>
</template>
