<script setup>
import { computed } from 'vue'
import ReceiptContent from '@/components/receipt/ReceiptContent.vue'
import { calculateTotals, pricingFrom } from '@/lib/pricing'

/**
 * Pratinjau struk langsung (FE-1.14): transaksi contoh dihitung ulang dengan aturan
 * yang sedang diubah, memakai komponen yang sama dengan struk sungguhan.
 */
const props = defineProps({
  settings: { type: Object, required: true }, // bentuk sama dengan GET /settings
  outlet: { type: Object, required: true }, // { code, name, address, phone, tax_rate, service_charge_rate, receipt_header, receipt_footer }
})

const SAMPLE = [
  { name: 'Kopi Susu', quantity: 2, unit_price: 15000, note: 'Es sedikit' },
  { name: 'Roti Bakar', quantity: 1, unit_price: 18000, note: null },
]

const lines = (text) => (text ?? '').split(/\r?\n/).filter((l) => l.trim())

const receipt = computed(() => {
  const s = props.settings
  const totals = calculateTotals(SAMPLE, pricingFrom(s, props.outlet))
  const custom = lines(props.outlet.receipt_header)
  const header = custom.length
    ? custom
    : [s.business?.name || props.outlet.name, props.outlet.address, props.outlet.phone && `Telp ${props.outlet.phone}`].filter(Boolean)
  if (s.business?.npwp) header.push(`NPWP ${s.business.npwp}`)

  const paid = Math.ceil(totals.grandTotal / 50000) * 50000

  return {
    header,
    number: `${props.outlet.code}-20261015-0042`,
    datetime: new Date(),
    cashier: s.receipt?.show_cashier ? 'Rina' : null,
    items: SAMPLE.map((i) => ({ ...i, total: i.unit_price * i.quantity })),
    totals: [
      { label: 'Subtotal', amount: totals.subtotal },
      totals.serviceCharge && { label: 'Service charge', amount: totals.serviceCharge },
      totals.taxTotal && { label: s.tax?.inclusive ? 'Pajak (termasuk)' : 'Pajak', amount: totals.taxTotal },
      totals.rounding && { label: 'Pembulatan', amount: totals.rounding },
    ].filter(Boolean),
    grand_total: totals.grandTotal,
    payments: [{ label: 'Tunai', amount: paid }],
    change_amount: paid - totals.grandTotal,
    footer: lines(props.outlet.receipt_footer),
    is_void: false,
  }
})

const width = computed(() => `${props.settings.receipt?.paper_width ?? 58}mm`)
</script>

<template>
  <div class="flex flex-col items-center gap-2">
    <p class="text-sm text-muted">Pratinjau struk · kertas {{ settings.receipt?.paper_width ?? 58 }} mm</p>
    <div class="rounded-sm border border-border-strong bg-white p-3" :style="{ width }">
      <ReceiptContent :receipt="receipt" />
    </div>
  </div>
</template>
