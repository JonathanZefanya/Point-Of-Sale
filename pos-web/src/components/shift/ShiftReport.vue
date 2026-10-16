<script setup>
import { computed } from 'vue'
import { formatDateTime, formatNumber, formatRupiah } from '@/lib/format'

/**
 * Laporan shift (Z-report) — tampil di layar dan dicetak lewat window.print().
 * Kelas .print-area membuat hanya bagian ini yang tercetak (lihat style.css).
 */
const props = defineProps({
  shift: { type: Object, required: true },
  report: { type: Object, required: true },
})

const rows = computed(() => [
  ['Modal awal', props.report.opening_cash],
  ['Kas masuk', props.report.cash_in],
  ['Kas keluar', -props.report.cash_out],
  ...(props.report.refunds ?? []).filter((r) => r.method === 'cash').map((r) => ['Refund tunai', -r.amount]),
])
</script>

<template>
  <div class="print-area w-full max-w-sm rounded border border-border bg-surface p-5 font-mono text-sm">
    <p class="text-center font-semibold">LAPORAN SHIFT</p>
    <p class="text-center">{{ shift.outlet?.code }} · {{ shift.outlet?.name }}</p>
    <hr class="my-3 border-dashed border-border-strong">

    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-0.5">
      <dt>Kasir</dt><dd class="text-right">{{ shift.user?.name }}</dd>
      <dt>Buka</dt><dd class="text-right">{{ formatDateTime(shift.opened_at) }}</dd>
      <dt>Tutup</dt><dd class="text-right">{{ formatDateTime(shift.closed_at) }}</dd>
      <dt>Transaksi</dt><dd class="text-right">{{ formatNumber(report.transaction_count) }}</dd>
      <dt>Total penjualan</dt><dd class="text-right">{{ formatRupiah(report.sales_total) }}</dd>
    </dl>

    <template v-if="report.payments?.length">
      <hr class="my-3 border-dashed border-border-strong">
      <p class="mb-1 font-semibold">Per metode bayar</p>
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-0.5">
        <template v-for="p in report.payments" :key="p.method">
          <dt>{{ p.label }}</dt><dd class="text-right">{{ formatRupiah(p.amount) }}</dd>
        </template>
      </dl>
    </template>

    <hr class="my-3 border-dashed border-border-strong">
    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-0.5">
      <template v-for="[label, value] in rows" :key="label">
        <dt>{{ label }}</dt><dd class="text-right">{{ formatRupiah(value) }}</dd>
      </template>
      <dt class="mt-1 font-semibold">Kas seharusnya</dt><dd class="mt-1 text-right font-semibold">{{ formatRupiah(report.expected_cash) }}</dd>
      <dt>Kas fisik</dt><dd class="text-right">{{ formatRupiah(report.actual_cash) }}</dd>
      <dt class="font-semibold">Selisih</dt>
      <dd :class="['text-right font-semibold', report.difference < 0 ? 'text-danger' : report.difference > 0 ? 'text-warning' : '']">
        {{ report.difference > 0 ? '+' : '' }}{{ formatRupiah(report.difference) }}
      </dd>
    </dl>
    <p v-if="shift.note" class="mt-3">Catatan: {{ shift.note }}</p>
  </div>
</template>
