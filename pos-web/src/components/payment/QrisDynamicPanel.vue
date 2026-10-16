<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import http from '@/lib/http'
import { formatRupiah } from '@/lib/format'

/**
 * QRIS dinamis Midtrans (3.5): buat QR sesuai nominal → pelanggan scan → status dicek tiap 3 detik.
 * Lunas → emit 'paid' dengan intent; baris pembayaran memakai intent_id sehingga server memverifikasinya.
 */
const props = defineProps({
  amount: { type: Number, default: 0 },
  outletId: { type: Number, required: true },
  paid: { type: Boolean, default: false }, // baris ini sudah lunas lewat QR
})

const emit = defineEmits(['paid'])

const intent = ref(null)
const creating = ref(false)
const error = ref('')
const now = ref(Date.now())
let timer = null

const secondsLeft = computed(() => (intent.value?.expires_at ? Math.max(0, Math.floor((new Date(intent.value.expires_at) - now.value) / 1000)) : 0))
const countdown = computed(() => `${Math.floor(secondsLeft.value / 60)}:${String(secondsLeft.value % 60).padStart(2, '0')}`)

function stop() {
  clearInterval(timer)
  timer = null
}

async function poll() {
  now.value = Date.now()
  if (!intent.value || intent.value.status !== 'pending') return stop()
  try {
    const { data } = await http.get(`/payments/qris/${intent.value.id}`, { silent: true })
    intent.value = data.data
    if (data.data.status === 'paid') {
      stop()
      emit('paid', data.data)
    }
  } catch {
    // koneksi putus sesaat: coba lagi di polling berikutnya
  }
}

async function create() {
  if (!props.amount || creating.value) return
  creating.value = true
  error.value = ''
  try {
    const { data } = await http.post('/payments/qris', { outlet_id: props.outletId, amount: props.amount }, { silent: true })
    intent.value = data.data
    stop()
    timer = setInterval(poll, 3000)
  } catch (e) {
    error.value = e.fieldErrors?.amount ?? e.userMessage
  } finally {
    creating.value = false
  }
}

// Nominal berubah → QR lama tidak berlaku untuk baris ini
watch(() => props.amount, () => {
  if (intent.value?.status === 'pending') {
    stop()
    intent.value = null
  }
})
onBeforeUnmount(stop)
</script>

<template>
  <div class="flex flex-col items-center gap-2 rounded border border-border p-3 text-center">
    <p v-if="paid" class="font-semibold text-success" role="status">QRIS lunas {{ formatRupiah(amount) }}. Tekan Selesai.</p>

    <template v-else-if="intent && intent.status === 'pending'">
      <img v-if="intent.qr_url" :src="intent.qr_url" :alt="`QRIS ${formatRupiah(intent.amount)}`" class="size-56 bg-white">
      <p class="font-semibold tabular-nums">{{ formatRupiah(intent.amount) }}</p>
      <p class="text-muted" role="status">Minta pelanggan scan dengan aplikasi bank/e-wallet. Menunggu pembayaran · {{ countdown }}</p>
    </template>

    <template v-else>
      <p v-if="intent?.status === 'expired'" class="text-warning">QR kedaluwarsa. Buat QR baru.</p>
      <p v-else-if="intent?.status === 'failed'" class="text-danger">Pembayaran QR gagal atau nominal tidak sesuai. Buat QR baru.</p>
      <Button size="lg" variant="primary" :disabled="!amount" :loading="creating" loading-text="Membuat QR..." @click="create">
        Tampilkan QR {{ formatRupiah(amount) }}
      </Button>
      <p class="text-sm text-muted">Atau pakai QRIS statis: isi nominal & no. referensi di bawah.</p>
    </template>

    <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
  </div>
</template>
