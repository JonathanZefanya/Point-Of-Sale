<script setup>
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import { formatRupiah } from '@/lib/format'
import CashQuickAmounts from './CashQuickAmounts.vue'
import PaymentMethodPicker from './PaymentMethodPicker.vue'
import QrisDynamicPanel from './QrisDynamicPanel.vue'

/**
 * Isian satu baris pembayaran di modal Bayar: metode, nominal cepat (tunai), QRIS dinamis, nominal, referensi.
 * `row` diubah langsung (objek reaktif milik usePaymentDraft).
 */
const props = defineProps({
  row: { type: Object, required: true }, // { method, amount, reference, intentId }
  due: { type: Number, required: true }, // sisa tagihan untuk baris ini
  methods: { type: Array, required: true },
  points: { type: Object, default: null }, // { balance, value } saldo poin pelanggan
  customerName: { type: String, default: '' },
  outletId: { type: Number, required: true },
  qrisDynamic: { type: Boolean, default: false },
})

const emit = defineEmits(['set-method'])

/* eslint-disable vue/no-mutating-props -- row adalah state draft bersama, sengaja diubah di tempat */
function onQrisPaid(intent) {
  Object.assign(props.row, { amount: intent.amount, reference: intent.order_id, intentId: intent.id })
}
</script>

<template>
  <PaymentMethodPicker :model-value="row.method" :methods="methods" @update:model-value="emit('set-method', $event)" />
  <p v-if="row.method === 'points' && points" class="text-muted">
    Saldo {{ customerName }}: {{ points.balance.toLocaleString('id-ID') }} poin ({{ formatRupiah(points.balance * points.value) }})
  </p>

  <CashQuickAmounts v-if="row.method === 'cash'" :due="due" :selected="row.amount" @select="row.amount = $event" />

  <QrisDynamicPanel
    v-if="row.method === 'qris' && qrisDynamic"
    :amount="row.amount ?? 0"
    :outlet-id="outletId"
    :paid="!!row.intentId"
    @paid="onQrisPaid"
  />
  <MoneyInput v-model="row.amount" :label="row.method === 'cash' ? 'Uang diterima' : 'Nominal'" size="lg" :disabled="!!row.intentId" />
  <Input v-if="row.method !== 'cash'" v-model="row.reference" label="No. referensi (opsional)" placeholder="mis. kode approval EDC" maxlength="100" :disabled="!!row.intentId" />
</template>
