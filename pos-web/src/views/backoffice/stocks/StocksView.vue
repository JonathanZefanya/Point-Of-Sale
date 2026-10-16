<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import StockAdjustModal from '@/components/stocks/StockAdjustModal.vue'
import StockStatusTabs from '@/components/stocks/StockStatusTabs.vue'
import StockTable from '@/components/stocks/StockTable.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import { useProductApi } from '@/composables/useProductApi'
import { useStockApi } from '@/composables/useStockApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { BACKOFFICE_ROLES } from '@/lib/roles'
import { useAuthStore } from '@/stores/auth'
import { useOutletStore } from '@/stores/outlet'

const api = useStockApi()
const productApi = useProductApi()
const auth = useAuthStore()
const outletStore = useOutletStore()
const router = useRouter()

const canManageCatalog = auth.hasRole(...BACKOFFICE_ROLES)
const categories = ref([])
const adjusting = ref(null)
const adjustOpen = ref(false)

const { filters, page, hasFilter, setPage, resetFilters } = useListQuery({ search: '', category_id: '', status: '' })
const { rows, meta, extra, loading, error, load } = usePaginatedList(api.list, () => ({
  ...filters.value,
  outlet_id: outletStore.current?.id,
  page: page.value,
  per_page: 20,
}))

// Outlet disimpan di store (bukan URL) karena dipakai bersama halaman lain
// Muat ulang bila outlet terpilih berbeda dari outlet data yang tampil (termasuk pilihan tersimpan saat halaman dibuka)
watch(
  () => [outletStore.current?.id, extra.value.summary?.outlet_id],
  ([selected, shown]) => {
    if (selected && shown && selected !== shown && !loading.value) load()
  },
)

const outletId = computed(() => outletStore.current?.id ?? extra.value.summary?.outlet_id)
const categoryOptions = computed(() => [{ value: '', label: 'Semua kategori' }, ...categories.value.map((c) => ({ value: String(c.id), label: c.name }))])

// Pencarian menunggu user berhenti mengetik
const search = ref(filters.value.search)
let timer = null
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => (filters.value = { ...filters.value, search: value }), 300)
})

function setFilter(key, value) {
  filters.value = { ...filters.value, [key]: value }
}

function openCard(row) {
  router.push({ name: 'stock-card', params: { productId: row.product_id, variantId: row.variant_id ?? '' } })
}

function openAdjust(row) {
  adjusting.value = row
  adjustOpen.value = true
}

onMounted(async () => {
  outletStore.ensureLoaded().catch(() => {})
  categories.value = await productApi.categories().catch(() => [])
})
</script>

<template>
  <BackofficeLayout>
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <StockStatusTabs :model-value="filters.status" :summary="extra.summary" @update:model-value="setFilter('status', $event)" />
        <Input v-model="search" type="search" placeholder="Cari nama, SKU, atau barcode" aria-label="Cari produk" class="w-72" />
        <Select
          v-if="categories.length"
          :model-value="filters.category_id"
          :options="categoryOptions"
          aria-label="Filter kategori"
          class="w-48"
          @update:model-value="setFilter('category_id', $event)"
        />
        <Button v-if="hasFilter" variant="ghost" @click="search = ''; resetFilters()">Hapus filter</Button>
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Daftar stok gagal dimuat." :description="error">
          <Button variant="primary" @click="load">Coba Lagi</Button>
        </EmptyState>
      </div>

      <template v-else>
        <StockTable :rows="rows" :loading="loading" :show-value="canManageCatalog" @open="openCard" @adjust="openAdjust">
          <template #empty>
            <EmptyState v-if="filters.status && !filters.search && !filters.category_id" :title="filters.status === 'out' ? 'Tidak ada produk yang habis.' : 'Tidak ada stok yang menipis.'" description="Semua stok di atas batas minimum.">
              <Button @click="resetFilters">Lihat Semua Stok</Button>
            </EmptyState>
            <EmptyState v-else-if="hasFilter" title="Tidak ada produk yang cocok dengan filter." description="Coba kata kunci lain atau hapus filter.">
              <Button @click="search = ''; resetFilters()">Hapus Filter</Button>
            </EmptyState>
            <EmptyState v-else title="Belum ada produk yang dilacak stoknya." description="Produk dengan &quot;Lacak stok&quot; aktif akan muncul di sini.">
              <Button v-if="canManageCatalog" variant="primary" @click="router.push({ name: 'product-create' })">Tambah Produk</Button>
            </EmptyState>
          </template>
        </StockTable>

        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>

    <StockAdjustModal v-if="outletId" v-model:open="adjustOpen" :item="adjusting" :outlet-id="outletId" @saved="load" />
  </BackofficeLayout>
</template>
