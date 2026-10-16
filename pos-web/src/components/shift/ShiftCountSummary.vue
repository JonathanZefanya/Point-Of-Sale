<script setup>
import { computed } from 'vue'
import { formatDateTime, formatNumber, formatRupiah } from '@/lib/format'

/**
 * Ringkasan sebelum kasir menghitung kas (F6.2). Angka kas versi sistem dan penjualan tunai
 * sengaja tidak ditampilkan agar kasir menghitung uang fisik apa adanya.
 */
const props = defineProps({
  shift: { type: Object, required: true },
  report: { type: Object, default: null },
})

const nonCash = computed(() => (props.report?.payments ?? []).filter((p) => p.method !== 'cash'))
</script>

<template>
  <div class="rounded border border-border bg-surface p-4">
    <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1">
      <dt class="text-muted">Shift dibuka</dt><dd class="text-right">{{ formatDateTime(shift.opened_at) }}</dd>
      <dt class="text-muted">Modal awal</dt><dd class="num">{{ formatRupiah(shift.opening_cash) }}</dd>
      <template v-if="report">
        <dt class="text-muted">Kas masuk</dt><dd class="num">{{ formatRupiah(report.cash_in) }}</dd>
        <dt class="text-muted">Kas keluar</dt><dd class="num">{{ formatRupiah(report.cash_out) }}</dd>
        <dt class="text-muted">Jumlah transaksi</dt><dd class="num">{{ formatNumber(report.transaction_count) }}</dd>
        <template v-for="p in nonCash" :key="p.method">
          <dt class="text-muted">{{ p.label }}</dt><dd class="num">{{ formatRupiah(p.amount) }}</dd>
        </template>
      </template>
    </dl>
  </div>
</template>
