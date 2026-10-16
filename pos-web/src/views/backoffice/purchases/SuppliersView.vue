<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import SupplierFormModal from '@/components/purchases/SupplierFormModal.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Table from '@/components/ui/Table.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import { usePurchaseApi } from '@/composables/usePurchaseApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatNumber } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/** Supplier (2.6): daftar, tambah/ubah, hapus. */
const api = usePurchaseApi()
const toast = useToastStore()
const router = useRouter()

const { filters, page, hasFilter, setPage, resetFilters } = useListQuery({ search: '' })
const { rows, meta, loading, error, load } = usePaginatedList(api.suppliers, () => ({ ...filters.value, page: page.value, per_page: 20 }))

const selected = ref(null)
const formOpen = ref(false)
const deleting = ref(null)
const deleteBusy = ref(false)

const search = ref(filters.value.search)
let timer = null
watch(search, (v) => {
  clearTimeout(timer)
  timer = setTimeout(() => (filters.value = { ...filters.value, search: v }), 300)
})

const columns = [
  { key: 'name', label: 'Supplier' },
  { key: 'phone', label: 'Telepon', width: '170px' },
  { key: 'open_orders', label: 'PO berjalan', align: 'right', width: '130px' },
  { key: 'actions', label: '', align: 'right', width: '140px' },
]

function openForm(supplier = null) {
  selected.value = supplier
  formOpen.value = true
}

async function remove() {
  deleteBusy.value = true
  try {
    await api.removeSupplier(deleting.value.id)
    toast.success(`Supplier ${deleting.value.name} dihapus.`)
    deleting.value = null
    load()
  } finally {
    deleteBusy.value = false
  }
}
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" @click="openForm()">Tambah Supplier</Button>
    </template>

    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <Input v-model="search" type="search" placeholder="Cari nama atau telepon" aria-label="Cari supplier" class="w-72" />
        <Button v-if="hasFilter" variant="ghost" @click="search = ''; resetFilters()">Hapus filter</Button>
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Daftar supplier gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
      </div>
      <template v-else>
        <Table :columns="columns" :rows="rows" :loading="loading" clickable @row-click="openForm">
          <template #cell-name="{ row }">
            <span class="block font-medium">{{ row.name }}</span>
            <span v-if="row.contact_name" class="block text-muted">{{ row.contact_name }}</span>
          </template>
          <template #cell-phone="{ value }">{{ value || '–' }}</template>
          <template #cell-open_orders="{ value }">{{ formatNumber(value) }}</template>
          <template #cell-actions="{ row }">
            <Button variant="ghost" @click.stop="router.push({ name: 'purchase-order-new', query: { supplier_id: row.id } })">Buat PO</Button>
          </template>
          <template #empty>
            <EmptyState v-if="hasFilter" title="Tidak ada supplier yang cocok." description="Coba kata kunci lain.">
              <Button @click="search = ''; resetFilters()">Hapus Filter</Button>
            </EmptyState>
            <EmptyState v-else title="Belum ada supplier." description="Tambahkan supplier sebelum membuat pesanan pembelian (PO).">
              <Button variant="primary" @click="openForm()">Tambah Supplier</Button>
            </EmptyState>
          </template>
        </Table>
        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>

    <SupplierFormModal v-model:open="formOpen" :supplier="selected" @saved="load" @delete="(s) => { formOpen = false; deleting = s }" />
    <ConfirmModal
      :open="!!deleting"
      :title="deleting ? `Hapus supplier ${deleting.name}?` : ''"
      confirm-label="Hapus Supplier"
      busy-label="Menghapus..."
      :busy="deleteBusy"
      @update:open="(v) => !v && (deleting = null)"
      @confirm="remove"
    >
      <p>Supplier hilang dari pilihan saat membuat PO. PO lama tetap menampilkan nama supplier ini.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
