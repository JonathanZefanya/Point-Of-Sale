<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Select from '@/components/ui/Select.vue'
import http from '@/lib/http'
import { formatNumber, formatRupiah } from '@/lib/format'
import { PAYMENT_METHODS } from '@/lib/paymentMethods'

/**
 * Refund sebagian/penuh (F5): pilih item & jumlah → kembali ke stok? → metode → alasan → PIN supervisor.
 * Perkiraan nominal memakai rumus yang sama dengan server; nilai akhir dari server.
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  sale: { type: Object, default: null },
})

const emit = defineEmits(['refunded'])

const lines = ref([])
const method = ref('cash')
const reason = ref('')
const pin = ref('')
const errors = ref({})
const message = ref('')
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen || !props.sale) return
  lines.value = props.sale.items
    .map((i) => ({ ...i, remaining: i.quantity - (i.refunded_quantity ?? 0), qty: 0, restock: true }))
    .filter((i) => i.remaining > 0)
  method.value = props.sale.payments?.[0]?.method ?? 'cash'
  reason.value = ''
  pin.value = ''
  errors.value = {}
  message.value = ''
})

const selected = computed(() => lines.value.filter((l) => l.qty > 0))
const estimate = computed(() => {
  const sale = props.sale
  if (!sale || !selected.value.length) return 0
  const everything = lines.value.every((l) => l.qty >= l.remaining)
  if (everything) return sale.grand_total - (sale.refunded_total ?? 0)
  const lineTotal = selected.value.reduce((s, l) => s + Math.floor((l.total * l.qty * 2 + l.quantity) / (l.quantity * 2)), 0)
  return Math.floor((sale.grand_total * lineTotal * 2 + sale.subtotal) / (sale.subtotal * 2))
})

function step(line, delta) {
  line.qty = Math.min(line.remaining, Math.max(0, line.qty + delta))
}

async function submit() {
  const next = {}
  if (!selected.value.length) next.items = 'Pilih minimal satu item dan jumlahnya.'
  if (reason.value.trim().length < 3) next.reason = 'Tulis alasan refund, mis. "Barang cacat".'
  if (!/^\d{6}$/.test(pin.value)) next.supervisor_pin = 'Minta pemilik/manajer memasukkan PIN 6 digit.'
  errors.value = next
  if (Object.keys(next).length) return

  saving.value = true
  message.value = ''
  try {
    const { data } = await http.post(`/sales/${props.sale.id}/refund`, {
      items: selected.value.map((l) => ({ sale_item_id: l.id, quantity: l.qty, restock: l.restock })),
      method: method.value,
      reason: reason.value.trim(),
      supervisor_pin: pin.value,
    }, { silent: true })
    open.value = false
    emit('refunded', data.data)
  } catch (e) {
    pin.value = ''
    errors.value = e.fieldErrors ?? {}
    if (!e.fieldErrors || e.fieldErrors.items || Object.keys(e.fieldErrors).some((k) => k.startsWith('items.'))) message.value = e.userMessage
  } finally {
    saving.value = false
  }
}

const stepButton = 'flex size-11 items-center justify-center rounded border border-border-strong bg-surface text-lg disabled:opacity-40'
</script>

<template>
  <Modal v-model:open="open" :title="sale ? `Refund ${sale.number}` : 'Refund'" size="md" :dismissible="!saving">
    <form v-if="sale" id="refund-form" class="cashier-root grid gap-4" novalidate @submit.prevent="submit">
      <ul class="divide-y divide-border rounded border border-border">
        <li v-for="line in lines" :key="line.id" class="grid gap-2 px-3 py-2">
          <div class="flex items-center gap-3">
            <span class="min-w-0 flex-1">
              <span class="block">{{ line.product_name }}</span>
              <span class="block text-sm text-muted">Bisa dikembalikan {{ formatNumber(line.remaining) }} dari {{ formatNumber(line.quantity) }}</span>
            </span>
            <button type="button" :class="stepButton" :disabled="line.qty <= 0" aria-label="Kurangi" @click="step(line, -1)">−</button>
            <span class="w-8 text-center tabular-nums">{{ line.qty }}</span>
            <button type="button" :class="stepButton" :disabled="line.qty >= line.remaining" aria-label="Tambah" @click="step(line, 1)">+</button>
          </div>
          <label v-if="line.qty > 0" class="inline-flex items-center gap-2 text-sm">
            <input v-model="line.restock" type="checkbox" class="size-4 accent-primary">
            Kembalikan ke stok (hilangkan centang bila barang rusak)
          </label>
        </li>
      </ul>
      <p v-if="errors.items" class="text-danger" role="alert">{{ errors.items }}</p>

      <div class="flex items-baseline justify-between rounded bg-surface-muted px-3 py-2">
        <span class="text-muted">Perkiraan uang dikembalikan</span>
        <span class="text-xl font-bold tabular-nums">{{ formatRupiah(estimate) }}</span>
      </div>

      <Select v-model="method" label="Dikembalikan lewat" :options="PAYMENT_METHODS" />
      <Input v-model="reason" label="Alasan" size="lg" required maxlength="500" placeholder="mis. Barang cacat" :error="errors.reason" @input="errors.reason = ''" />
      <Input
        v-model="pin"
        label="PIN supervisor"
        type="password"
        size="lg"
        inputmode="numeric"
        maxlength="6"
        autocomplete="off"
        hint="Diisi oleh pemilik atau manajer outlet."
        :error="errors.supervisor_pin"
        @input="errors.supervisor_pin = ''"
      />
      <p v-if="message" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ message }}</p>
    </form>
    <template #footer>
      <Button size="lg" :disabled="saving" @click="open = false">Kembali</Button>
      <Button type="submit" form="refund-form" variant="danger" size="lg" :loading="saving" loading-text="Memproses...">Proses Refund</Button>
    </template>
  </Modal>
</template>
