<script setup>
import { ref, watch } from 'vue'
import CustomerFormModal from '@/components/customers/CustomerFormModal.vue'
import CustomerHistoryModal from '@/components/customers/CustomerHistoryModal.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import Table from '@/components/ui/Table.vue'
import { CUSTOMER_GROUPS, useCustomerApi } from '@/composables/useCustomerApi'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatNumber, formatRupiah } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/** Pelanggan (2.3): daftar, tambah/ubah, riwayat belanja. */
const api = useCustomerApi()
const toast = useToastStore()

const { filters, page, hasFilter, setPage, resetFilters } = useListQuery({ search: '', group: '' })
const { rows, meta, loading, error, load } = usePaginatedList(api.list, () => ({ ...filters.value, page: page.value, per_page: 20 }))

const selected = ref(null)
const formOpen = ref(false)
const historyOpen = ref(false)
const deleting = ref(null)
const deleteBusy = ref(false)

const search = ref(filters.value.search)
let timer = null
watch(search, (v) => {
  clearTimeout(timer)
  timer = setTimeout(() => (filters.value = { ...filters.value, search: v }), 300)
})

const columns = [
  { key: 'name', label: 'Pelanggan' },
  { key: 'group_label', label: 'Grup', width: '120px' },
  { key: 'visits', label: 'Kunjungan', align: 'right', width: '110px' },
  { key: 'total_spent', label: 'Total belanja', align: 'right', width: '150px' },
  { key: 'points', label: 'Poin', align: 'right', width: '100px' },
]

function openForm(customer = null) {
  selected.value = customer
  historyOpen.value = false
  formOpen.value = true
}

function openHistory(customer) {
  selected.value = customer
  historyOpen.value = true
}

async function remove() {
  deleteBusy.value = true
  try {
    await api.remove(deleting.value.id)
    toast.success(`Pelanggan ${deleting.value.name} dihapus.`)
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
      <Button variant="primary" @click="openForm()">Tambah Pelanggan</Button>
    </template>

    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <Input v-model="search" type="search" placeholder="Cari nama atau nomor HP" aria-label="Cari pelanggan" class="w-72" />
        <Select
          :model-value="filters.group"
          :options="[{ value: '', label: 'Semua grup' }, ...CUSTOMER_GROUPS]"
          aria-label="Filter grup"
          class="w-40"
          @update:model-value="filters = { ...filters, group: $event }"
        />
        <Button v-if="hasFilter" variant="ghost" @click="search = ''; resetFilters()">Hapus filter</Button>
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Daftar pelanggan gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
      </div>
      <template v-else>
        <Table :columns="columns" :rows="rows" :loading="loading" clickable @row-click="openHistory">
          <template #cell-name="{ row }">
            <span class="block font-medium">{{ row.name }}</span>
            <span class="block text-muted">{{ row.phone || 'Tanpa nomor HP' }}</span>
          </template>
          <template #cell-visits="{ value }">{{ formatNumber(value) }}</template>
          <template #cell-total_spent="{ value }">{{ formatRupiah(value) }}</template>
          <template #cell-points="{ value }">{{ formatNumber(value) }}</template>
          <template #empty>
            <EmptyState v-if="hasFilter" title="Tidak ada pelanggan yang cocok." description="Coba kata kunci lain atau hapus filter.">
              <Button @click="search = ''; resetFilters()">Hapus Filter</Button>
            </EmptyState>
            <EmptyState v-else title="Belum ada pelanggan." description="Pelanggan bisa ditambahkan di sini atau langsung dari layar kasir saat transaksi.">
              <Button variant="primary" @click="openForm()">Tambah Pelanggan</Button>
            </EmptyState>
          </template>
        </Table>
        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>

    <CustomerHistoryModal v-model:open="historyOpen" :customer="selected" @edit="openForm" />
    <CustomerFormModal v-model:open="formOpen" :customer="selected" @saved="load" @delete="(c) => { formOpen = false; deleting = c }" />
    <ConfirmModal
      :open="!!deleting"
      :title="deleting ? `Hapus pelanggan ${deleting.name}?` : ''"
      confirm-label="Hapus Pelanggan"
      busy-label="Menghapus..."
      :busy="deleteBusy"
      @update:open="(v) => !v && (deleting = null)"
      @confirm="remove"
    >
      <p>Pelanggan hilang dari daftar dan tidak bisa dipilih di kasir. Riwayat transaksinya tetap tersimpan.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
