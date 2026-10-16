<script setup>
import { computed, ref, watch } from 'vue'
import TableFormModal from '@/components/tables/TableFormModal.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Table from '@/components/ui/Table.vue'
import { useTableApi } from '@/composables/useTableApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatRupiah } from '@/lib/format'
import { useOutletStore } from '@/stores/outlet'
import { useToastStore } from '@/stores/toast'

/** Meja (F&B 3.3) untuk outlet terpilih. Bila outlet punya meja aktif, layar kasir menampilkan tombol Meja. */
const api = useTableApi()
const outletStore = useOutletStore()
const toast = useToastStore()

const rows = ref([])
const loading = ref(true)
const error = ref('')
const selected = ref(null)
const formOpen = ref(false)
const deleting = ref(null)
const deleteBusy = ref(false)
const outletId = computed(() => outletStore.current?.id ?? null)

const columns = [
  { key: 'name', label: 'Meja' },
  { key: 'area', label: 'Area', width: '180px' },
  { key: 'capacity', label: 'Kursi', align: 'right', width: '90px' },
  { key: 'status', label: 'Status', width: '220px' },
]

async function load() {
  if (!outletId.value) return
  loading.value = true
  error.value = ''
  try {
    rows.value = await api.list(outletId.value)
  } catch (e) {
    error.value = e.userMessage
  } finally {
    loading.value = false
  }
}

function openForm(table = null) {
  selected.value = table
  formOpen.value = true
}

async function remove() {
  deleteBusy.value = true
  try {
    await api.remove(deleting.value.id)
    toast.success(`${deleting.value.name} dihapus.`)
    deleting.value = null
    load()
  } finally {
    deleteBusy.value = false
  }
}

watch(outletId, load, { immediate: true })
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" :disabled="!outletId" @click="openForm()">Tambah Meja</Button>
    </template>

    <div v-if="error" class="rounded border border-border bg-surface">
      <EmptyState title="Daftar meja gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>
    <Table v-else :columns="columns" :rows="rows" :loading="loading" clickable @row-click="openForm">
      <template #cell-name="{ value }"><span class="font-medium">{{ value }}</span></template>
      <template #cell-area="{ value }">{{ value || '–' }}</template>
      <template #cell-status="{ row }">
        <Badge v-if="!row.is_active" tone="neutral">Tidak dipakai</Badge>
        <Badge v-else-if="row.occupied" tone="warning">Terisi · {{ formatRupiah(row.open_sale.grand_total) }}</Badge>
        <Badge v-else tone="success">Kosong</Badge>
      </template>
      <template #empty>
        <EmptyState title="Belum ada meja." description="Tambahkan meja untuk memakai bill per meja, pindah & gabung meja di layar kasir. Toko tanpa meja tidak perlu mengisi ini.">
          <Button variant="primary" @click="openForm()">Tambah Meja</Button>
        </EmptyState>
      </template>
    </Table>

    <TableFormModal v-model:open="formOpen" :table="selected" :outlet-id="outletId" @saved="load" @delete="(t) => { formOpen = false; deleting = t }" />
    <ConfirmModal
      :open="!!deleting"
      :title="deleting ? `Hapus ${deleting.name}?` : ''"
      confirm-label="Hapus Meja"
      busy-label="Menghapus..."
      :busy="deleteBusy"
      @update:open="(v) => !v && (deleting = null)"
      @confirm="remove"
    >
      <p>Meja hilang dari layar kasir. Riwayat transaksi tetap menyimpan nama mejanya.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
