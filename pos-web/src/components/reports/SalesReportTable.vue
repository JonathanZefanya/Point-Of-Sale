<script setup>
import Table from '@/components/ui/Table.vue'
import { formatDate, formatNumber, formatRupiah } from '@/lib/format'

defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const columns = [
  { key: 'date', label: 'Tanggal' },
  { key: 'transactions', label: 'Transaksi', align: 'right' },
  { key: 'gross', label: 'Penjualan kotor', align: 'right' },
  { key: 'discount', label: 'Diskon', align: 'right' },
  { key: 'service_charge', label: 'Service', align: 'right' },
  { key: 'tax', label: 'Pajak', align: 'right' },
  { key: 'refunds', label: 'Retur', align: 'right' },
  { key: 'net', label: 'Penjualan bersih', align: 'right' },
]
</script>

<template>
  <Table :columns="columns" :rows="rows" row-key="date" :loading="loading">
    <template #cell-date="{ value }">{{ formatDate(value) }}</template>
    <template #cell-transactions="{ value }">{{ formatNumber(value) }}</template>
    <template #cell-gross="{ value }">{{ formatRupiah(value) }}</template>
    <template #cell-discount="{ value }">{{ formatRupiah(value) }}</template>
    <template #cell-service_charge="{ value }">{{ formatRupiah(value) }}</template>
    <template #cell-tax="{ value }">{{ formatRupiah(value) }}</template>
    <template #cell-refunds="{ value }"><span :class="value && 'text-warning'">{{ formatRupiah(value) }}</span></template>
    <template #cell-net="{ value }"><span class="font-medium">{{ formatRupiah(value) }}</span></template>
    <template #empty><slot name="empty" /></template>
  </Table>
</template>
