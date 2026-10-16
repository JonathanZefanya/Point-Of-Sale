<script setup>
import Badge from '@/components/ui/Badge.vue'
import { formatDateTime, formatNumber, formatRupiah } from '@/lib/format'

// Rincian transaksi: item, total, pembayaran, status void.
defineProps({
  sale: { type: Object, required: true },
})

const statusTone = { completed: 'success', void: 'danger', refunded: 'warning', partial_refund: 'warning', held: 'neutral' }
</script>

<template>
  <div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <p class="font-mono font-medium">{{ sale.number }}</p>
        <p class="text-sm text-muted">{{ formatDateTime(sale.completed_at) }} · {{ sale.cashier?.name }}</p>
      </div>
      <Badge :tone="statusTone[sale.status]">{{ sale.status_label }}</Badge>
    </div>

    <p v-if="sale.status === 'void'" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger">
      Dibatalkan {{ formatDateTime(sale.voided_at) }} oleh {{ sale.voided_by?.name }}. Alasan: {{ sale.void_reason }}
    </p>

    <div v-for="r in sale.refunds ?? []" :key="r.id" class="rounded border border-warning/30 bg-warning-soft px-3 py-2 text-warning">
      Refund {{ formatRupiah(r.amount) }} lewat {{ r.method_label }} · {{ formatDateTime(r.created_at) }} · disetujui {{ r.approved_by?.name }}. Alasan: {{ r.reason }}
    </div>

    <ul class="divide-y divide-border rounded border border-border">
      <li v-for="item in sale.items" :key="item.id" class="flex gap-3 px-3 py-2">
        <span class="min-w-0 flex-1">
          <span class="block">{{ item.product_name }}</span>
          <span class="block text-sm text-muted tabular-nums">{{ formatNumber(item.quantity) }} × {{ formatNumber(item.unit_price) }}</span>
          <span v-if="item.modifiers?.length" class="block text-sm text-muted">{{ item.modifiers.map((m) => m.name).join(', ') }}</span>
          <span v-if="item.note" class="block text-sm text-muted">Catatan: {{ item.note }}</span>
          <span v-if="item.refunded_quantity" class="block text-sm text-warning">Dikembalikan {{ formatNumber(item.refunded_quantity) }}</span>
        </span>
        <span class="num">{{ formatNumber(item.total) }}</span>
      </li>
    </ul>

    <dl class="grid grid-cols-2 gap-y-1">
      <dt class="text-muted">Subtotal</dt><dd class="num">{{ formatNumber(sale.subtotal) }}</dd>
      <template v-if="sale.service_charge"><dt class="text-muted">Service charge</dt><dd class="num">{{ formatNumber(sale.service_charge) }}</dd></template>
      <template v-if="sale.tax_total"><dt class="text-muted">Pajak</dt><dd class="num">{{ formatNumber(sale.tax_total) }}</dd></template>
      <template v-if="sale.rounding"><dt class="text-muted">Pembulatan</dt><dd class="num">{{ formatNumber(sale.rounding) }}</dd></template>
      <dt class="font-semibold">Total</dt><dd class="num font-semibold">{{ formatRupiah(sale.grand_total) }}</dd>
      <template v-for="p in sale.payments" :key="p.method + p.amount">
        <dt class="text-muted">{{ p.label }}<span v-if="p.reference"> · {{ p.reference }}</span></dt><dd class="num">{{ formatRupiah(p.amount) }}</dd>
      </template>
      <template v-if="sale.change_amount"><dt class="text-muted">Kembalian</dt><dd class="num">{{ formatRupiah(sale.change_amount) }}</dd></template>
    </dl>
  </div>
</template>
