<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ImportProductsModal from '@/components/products/ImportProductsModal.vue'
import ProductFilters from '@/components/products/ProductFilters.vue'
import ProductTable from '@/components/products/ProductTable.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import { useProductApi } from '@/composables/useProductApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { BACKOFFICE_ROLES } from '@/lib/roles'
import { useAuthStore } from '@/stores/auth'

const api = useProductApi()
const auth = useAuthStore()
const router = useRouter()

const categories = ref([])
const importOpen = ref(false)
const exporting = ref(false)

/** Export mengikuti filter yang sedang dipakai. */
async function exportExcel() {
  exporting.value = true
  try {
    await api.exportExcel({ search: filters.value.search, category_id: filters.value.category_id, is_active: filters.value.is_active })
  } finally {
    exporting.value = false
  }
}
const canManage = auth.hasRole(...BACKOFFICE_ROLES)

const { filters, page, hasFilter, setPage, resetFilters } = useListQuery({ search: '', category_id: '', is_active: '' })
const { rows: products, meta, loading, error, load } = usePaginatedList(api.list, () => ({
  ...filters.value,
  page: page.value,
  per_page: 20,
  sort: 'name',
}))

function openProduct(product) {
  router.push({ name: 'product-edit', params: { id: product.id } })
}

onMounted(async () => {
  try {
    categories.value = await api.categories()
  } catch {
    // filter kategori tetap bisa dipakai tanpa daftar; error sudah ditampilkan lewat toast
  }
})
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <template v-if="canManage">
        <Button variant="ghost" @click="importOpen = true">Import</Button>
        <Button variant="ghost" :loading="exporting" loading-text="Menyiapkan..." @click="exportExcel">Export Excel</Button>
        <Button variant="primary" @click="router.push({ name: 'product-create' })">Tambah Produk</Button>
      </template>
    </template>

    <div class="flex flex-col gap-3">
      <ProductFilters v-model="filters" :categories="categories" :has-filter="hasFilter" @reset="resetFilters" />

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Daftar produk gagal dimuat." :description="error">
          <Button variant="primary" @click="load">Coba Lagi</Button>
        </EmptyState>
      </div>

      <template v-else>
        <ProductTable :products="products" :loading="loading" :show-cost="canManage" @open="openProduct">
          <template #empty>
            <EmptyState v-if="hasFilter" title="Tidak ada produk yang cocok dengan filter." description="Coba kata kunci lain atau hapus filter.">
              <Button @click="resetFilters">Hapus Filter</Button>
            </EmptyState>
            <EmptyState v-else title="Belum ada produk." description="Tambahkan produk pertama agar bisa dijual di layar kasir.">
              <Button variant="primary" @click="router.push({ name: 'product-create' })">Tambah Produk</Button>
              <Button v-if="canManage" @click="importOpen = true">Import dari Excel</Button>
            </EmptyState>
          </template>
        </ProductTable>

        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>

    <ImportProductsModal v-if="canManage" v-model:open="importOpen" @imported="load" />
  </BackofficeLayout>
</template>
