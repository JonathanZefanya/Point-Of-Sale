<script setup>
import { ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import http from '@/lib/http'

/**
 * Kirim struk via WhatsApp (3.6): pesan + link struk online, dibuka lewat wa.me (tanpa provider).
 * Nomor pelanggan terisi otomatis bila ada; kosongkan untuk memilih kontak di WhatsApp.
 */
const props = defineProps({
  saleId: { type: Number, required: true },
})

const open = ref(false)
const loading = ref(false)
const share = ref(null)
const phone = ref('')
const error = ref('')

async function start() {
  loading.value = true
  try {
    const { data } = await http.get(`/sales/${props.saleId}/share`)
    share.value = data.data
    phone.value = data.data.phone ?? ''
    error.value = ''
    open.value = true
  } finally {
    loading.value = false
  }
}

/** 0812… / +62812… → 62812… */
function normalize(value) {
  const digits = value.replace(/\D+/g, '')
  return digits.startsWith('0') ? `62${digits.slice(1)}` : digits
}

function send() {
  const number = normalize(phone.value)
  if (phone.value && number.length < 9) {
    error.value = 'Nomor WhatsApp terlalu pendek. Contoh: 081234567890.'
    return
  }
  window.open(`https://wa.me/${number}?text=${encodeURIComponent(share.value.text)}`, '_blank', 'noopener')
  open.value = false
}
</script>

<template>
  <Button size="lg" :loading="loading" loading-text="Menyiapkan..." @click="start">Kirim WhatsApp</Button>

  <Modal v-model:open="open" title="Kirim struk via WhatsApp" size="sm">
    <form id="wa-form" class="cashier-root flex flex-col gap-3" novalidate @submit.prevent="send">
      <Input v-model="phone" label="Nomor WhatsApp pelanggan" inputmode="tel" placeholder="081234567890" hint="Kosongkan untuk memilih kontak di WhatsApp." :error="error" size="lg" />
      <p class="text-muted">Pesan yang dikirim:</p>
      <pre class="whitespace-pre-wrap rounded border border-border bg-surface-muted p-2 font-sans text-sm">{{ share?.text }}</pre>
    </form>
    <template #footer>
      <Button size="lg" @click="open = false">Batal</Button>
      <Button type="submit" form="wa-form" variant="primary" size="lg">Buka WhatsApp</Button>
    </template>
  </Modal>
</template>
