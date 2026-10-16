<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import Table from '@/components/ui/Table.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import { PO_STATUSES, usePurchaseApi } from '@/composables/usePurchaseApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatDate, formatNumber, formatRupiah } from '@/lib/format'

/** Pembelian (F9): daftar PO, filter status. Klik baris → detail & terima barang. */
const api = usePurchaseApi()
const router = useRouter()

const { filters, page, hasFilter, setPage, resetFilters } = useListQuery({ search: '', status: '' })
const { rows, meta, loading, error, load } = usePaginatedList(api.orders, () => ({ ...filters.value, page: page.value, per_page: 20 }))

const search = ref(filters.value.search)
let timer = null
watch(search, (v) => {
  clearTimeout(timer)
  timer = setTimeout(() => (filters.value = { ...filters.value, search: v }), 300)
})

const statusTone = (value) => PO_STATUSES.find((s) => s.value === value)?.tone ?? 'neutral'

const columns = [
  { key: 'number', label: 'No. PO', width: '220px' },
  { key: 'supplier', label: 'Supplier' },
  { key: 'created_at', label: 'Tanggal', width: '130px' },
  { key: 'items_count', label: 'Produk', align: 'right', width: '90px' },
  { key: 'total', label: 'Total', align: 'right', width: '150px' },
  { key: 'status', label: 'Status', width: '160px' },
]
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="ghost" @click="router.push({ name: 'suppliers' })">Supplier</Button>
      <Button variant="primary" @click="router.push({ name: 'purchase-order-new' })">Buat PO</Button>
    </template>

    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <Input v-model="search" type="search" placeholder="Cari no. PO atau supplier" aria-label="Cari PO" class="w-72" />
        <Select
          :model-value="filters.status"
          :options="[{ value: '', label: 'Semua status' }, ...PO_STATUSES]"
          aria-label="Filter status"
          class="w-48"
          @update:model-value="filters = { ...filters, status: $event }"
        />
        <Button v-if="hasFilter" variant="ghost" @click="search = ''; resetFilters()">Hapus filter</Button>
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Daftar PO gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
      </div>
      <template v-else>
        <Table :columns="columns" :rows="rows" :loading="loading" clickable @row-click="(row) => router.push({ name: 'purchase-order', params: { id: row.id } })">
          <template #cell-number="{ value }"><span class="font-mono">{{ value }}</span></template>
          <template #cell-supplier="{ row }">{{ row.supplier?.name }}</template>
          <template #cell-created_at="{ value }">{{ formatDate(value) }}</template>
          <template #cell-items_count="{ value }">{{ formatNumber(value) }}</template>
          <template #cell-total="{ value }">{{ formatRupiah(value) }}</template>
          <template #cell-status="{ row }"><Badge :tone="statusTone(row.status)">{{ row.status_label }}</Badge></template>
          <template #empty>
            <EmptyState v-if="hasFilter" title="Tidak ada PO yang cocok." description="Coba kata kunci lain atau hapus filter.">
              <Button @click="search = ''; resetFilters()">Hapus Filter</Button>
            </EmptyState>
            <EmptyState v-else title="Belum ada pesanan pembelian." description="Buat PO ke supplier; stok bertambah saat barang diterima.">
              <Button variant="primary" @click="router.push({ name: 'purchase-order-new' })">Buat PO</Button>
            </EmptyState>
          </template>
        </Table>
        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>
  </BackofficeLayout>
</template>
