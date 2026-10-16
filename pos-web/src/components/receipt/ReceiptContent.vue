<script setup>
import { formatDateTime, formatNumber } from '@/lib/format'

/**
 * Isi struk thermal. Dipakai untuk cetak (ReceiptPrintArea) dan pratinjau di pengaturan.
 * Font monospace, lebar mengikuti kertas 58/80mm.
 */
defineProps({
  receipt: { type: Object, required: true },
  reprint: { type: Boolean, default: false },
})
</script>

<template>
  <div class="font-mono text-[11px] leading-snug text-black">
    <div class="text-center">
      <p v-for="(line, i) in receipt.header" :key="i" :class="i === 0 && 'text-[13px] font-bold'">{{ line }}</p>
    </div>
    <p v-if="receipt.is_void" class="my-1 border border-black text-center font-bold">*** DIBATALKAN ***</p>
    <p v-if="reprint" class="my-1 text-center font-bold">-- CETAK ULANG --</p>

    <hr class="my-1.5 border-dashed border-black">
    <p>{{ receipt.number }}</p>
    <p v-if="receipt.customer">Pelanggan: {{ receipt.customer }}</p>
    <p v-if="receipt.order_type">{{ receipt.order_type }}<template v-if="receipt.table"> · {{ receipt.table }}</template></p>
    <p>{{ formatDateTime(receipt.datetime) }}<span v-if="receipt.cashier"> · {{ receipt.cashier }}</span></p>
    <hr class="my-1.5 border-dashed border-black">

    <div v-for="(item, i) in receipt.items" :key="i" class="mb-1">
      <p>{{ item.name }}</p>
      <p class="flex justify-between gap-2">
        <span>{{ formatNumber(item.quantity) }} x {{ formatNumber(item.unit_price) }}</span>
        <span>{{ formatNumber(item.total) }}</span>
      </p>
      <p v-for="(m, j) in item.modifiers ?? []" :key="j" class="pl-2">+ {{ m }}</p>
      <p v-if="item.note" class="pl-2">* {{ item.note }}</p>
    </div>

    <hr class="my-1.5 border-dashed border-black">
    <p v-for="row in receipt.totals" :key="row.label" class="flex justify-between gap-2">
      <span>{{ row.label }}</span><span>{{ formatNumber(row.amount) }}</span>
    </p>
    <p class="mt-0.5 flex justify-between gap-2 text-[13px] font-bold">
      <span>TOTAL</span><span>{{ formatNumber(receipt.grand_total) }}</span>
    </p>
    <p v-for="(p, i) in receipt.payments" :key="i" class="flex justify-between gap-2">
      <span>{{ p.label }}</span><span>{{ formatNumber(p.amount) }}</span>
    </p>
    <p v-if="receipt.change_amount" class="flex justify-between gap-2">
      <span>Kembalian</span><span>{{ formatNumber(receipt.change_amount) }}</span>
    </p>

    <template v-if="receipt.footer.length">
      <hr class="my-1.5 border-dashed border-black">
      <p v-for="(line, i) in receipt.footer" :key="i" class="text-center">{{ line }}</p>
    </template>
  </div>
</template>
