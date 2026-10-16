<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import MovementTable from '@/components/stocks/MovementTable.vue'
import StockAdjustModal from '@/components/stocks/StockAdjustModal.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import { useStockApi } from '@/composables/useStockApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatNumber } from '@/lib/format'
import { useOutletStore } from '@/stores/outlet'

/** Kartu stok: riwayat setiap perubahan stok satu produk di satu outlet. */
const props = defineProps({
  productId: { type: String, required: true },
  variantId: { type: String, default: '' }, // kartu stok per varian
})

const api = useStockApi()
const outletStore = useOutletStore()
const adjustOpen = ref(false)

const TYPE_OPTIONS = [
  { value: '', label: 'Semua jenis' },
  { value: 'initial', label: 'Stok Awal' },
  { value: 'sale', label: 'Penjualan' },
  { value: 'sale_void', label: 'Pembatalan Penjualan' },
  { value: 'refund', label: 'Pengembalian' },
  { value: 'purchase', label: 'Pembelian' },
  { value: 'adjustment', label: 'Penyesuaian' },
  { value: 'opname', label: 'Stock Opname' },
  { value: 'transfer_out', label: 'Transfer Keluar' },
  { value: 'transfer_in', label: 'Transfer Masuk' },
]

const { filters, page, hasFilter, setPage, resetFilters } = useListQuery({ type: '', from: '', to: '' })
const { rows, meta, extra, loading, error, load } = usePaginatedList(
  (params) => api.movements(props.productId, params),
  () => ({ ...filters.value, variant_id: props.variantId || undefined, outlet_id: outletStore.current?.id, page: page.value, per_page: 20 }),
)

const product = computed(() => extra.value.product ?? null)
const adjustItem = computed(() => product.value && { ...product.value, product_id: product.value.id })

watch(
  () => [outletStore.current?.id, product.value?.outlet_id],
  ([selected, shown]) => {
    if (selected && shown && selected !== shown && !loading.value) load()
  },
)

function setFilter(key, value) {
  filters.value = { ...filters.value, [key]: value }
}

onMounted(() => outletStore.ensureLoaded().catch(() => {}))
</script>

<template>
  <BackofficeLayout :title="product ? `Kartu Stok · ${product.name}` : 'Kartu Stok'">
    <div class="flex flex-col gap-3">
      <RouterLink :to="{ name: 'stocks' }" class="text-muted hover:text-text">← Kembali ke daftar stok</RouterLink>

      <div v-if="product" class="flex flex-wrap items-center justify-between gap-4 rounded border border-border bg-surface px-5 py-4">
        <div>
          <p class="font-mono text-muted">{{ product.sku }}</p>
          <p class="text-base font-semibold">{{ product.name }}</p>
        </div>
        <div class="flex items-center gap-6">
          <div class="text-right">
            <p class="text-muted">Stok saat ini</p>
            <p class="text-2xl font-bold tabular-nums">{{ formatNumber(product.quantity) }} <span class="text-base font-normal text-muted">{{ product.unit }}</span></p>
          </div>
          <Button variant="primary" @click="adjustOpen = true">Sesuaikan Stok</Button>
        </div>
      </div>

      <div class="flex flex-wrap items-end gap-2">
        <Select :model-value="filters.type" :options="TYPE_OPTIONS" aria-label="Filter jenis" class="w-52" @update:model-value="setFilter('type', $event)" />
        <Input :model-value="filters.from" type="date" aria-label="Dari tanggal" class="w-40" @change="setFilter('from', $event.target.value)" />
        <span class="pb-2 text-muted">sampai</span>
        <Input :model-value="filters.to" type="date" aria-label="Sampai tanggal" class="w-40" @change="setFilter('to', $event.target.value)" />
        <Button v-if="hasFilter" variant="ghost" @click="resetFilters">Hapus filter</Button>
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Kartu stok gagal dimuat." :description="error">
          <Button variant="primary" @click="load">Coba Lagi</Button>
        </EmptyState>
      </div>

      <template v-else>
        <MovementTable :rows="rows" :loading="loading">
          <template #empty>
            <EmptyState v-if="hasFilter" title="Tidak ada pergerakan stok pada filter ini." description="Ubah jenis atau rentang tanggal.">
              <Button @click="resetFilters">Hapus Filter</Button>
            </EmptyState>
            <EmptyState v-else title="Belum ada pergerakan stok di outlet ini." description="Stok bertambah lewat penyesuaian atau pembelian, dan berkurang saat terjual.">
              <Button variant="primary" @click="adjustOpen = true">Sesuaikan Stok</Button>
            </EmptyState>
          </template>
        </MovementTable>
        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>

    <StockAdjustModal
      v-if="adjustItem"
      v-model:open="adjustOpen"
      :item="adjustItem"
      :outlet-id="product.outlet_id"
      @saved="load"
    />
  </BackofficeLayout>
</template>
