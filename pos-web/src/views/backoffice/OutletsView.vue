<script setup>
import { onMounted, ref } from 'vue'
import OutletFormModal from '@/components/outlets/OutletFormModal.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Table from '@/components/ui/Table.vue'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import http from '@/lib/http'
import { useOutletStore } from '@/stores/outlet'
import { useToastStore } from '@/stores/toast'

/** Outlet (2.8): daftar, tambah/ubah, hapus. Hanya pemilik. Staf ditugaskan ke outlet di menu Karyawan. */
const outletStore = useOutletStore()
const toast = useToastStore()

const rows = ref([])
const loading = ref(true)
const error = ref('')
const selected = ref(null)
const formOpen = ref(false)
const deleting = ref(null)
const deleteBusy = ref(false)

const columns = [
  { key: 'code', label: 'Kode', width: '110px' },
  { key: 'name', label: 'Outlet' },
  { key: 'phone', label: 'Telepon', width: '170px' },
  { key: 'tax_rate', label: 'Pajak', align: 'right', width: '100px' },
  { key: 'service_charge_rate', label: 'Service', align: 'right', width: '100px' },
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    rows.value = (await http.get('/outlets', { params: { per_page: 100 } })).data.data
  } catch (e) {
    error.value = e.userMessage
  } finally {
    loading.value = false
  }
}

/** Daftar outlet di header ikut diperbarui. */
async function refresh() {
  await load()
  outletStore.reset()
  outletStore.ensureLoaded().catch(() => {})
}

function openForm(outlet = null) {
  selected.value = outlet
  formOpen.value = true
}

async function remove() {
  deleteBusy.value = true
  try {
    await http.delete(`/outlets/${deleting.value.id}`)
    toast.success(`Outlet ${deleting.value.name} dihapus.`)
    deleting.value = null
    refresh()
  } finally {
    deleteBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" @click="openForm()">Tambah Outlet</Button>
    </template>

    <div v-if="error" class="rounded border border-border bg-surface">
      <EmptyState title="Daftar outlet gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>
    <Table v-else :columns="columns" :rows="rows" :loading="loading" clickable @row-click="openForm">
      <template #cell-code="{ value }"><span class="font-mono font-medium">{{ value }}</span></template>
      <template #cell-name="{ row }">
        <span class="block font-medium">{{ row.name }}</span>
        <span v-if="row.address" class="block text-muted">{{ row.address }}</span>
      </template>
      <template #cell-phone="{ value }">{{ value || '–' }}</template>
      <template #cell-tax_rate="{ value }">{{ Number(value).toLocaleString('id-ID') }}%</template>
      <template #cell-service_charge_rate="{ value }">{{ Number(value).toLocaleString('id-ID') }}%</template>
      <template #empty>
        <EmptyState title="Belum ada outlet." description="Tambahkan outlet pertama untuk mulai berjualan.">
          <Button variant="primary" @click="openForm()">Tambah Outlet</Button>
        </EmptyState>
      </template>
    </Table>
    <p class="mt-3 text-muted">Atur staf yang bekerja di setiap outlet lewat menu Karyawan. Harga khusus per outlet diatur di form produk → Pengaturan lanjutan.</p>

    <OutletFormModal v-model:open="formOpen" :outlet="selected" @saved="refresh" @delete="(o) => { formOpen = false; deleting = o }" />
    <ConfirmModal
      :open="!!deleting"
      :title="deleting ? `Hapus outlet ${deleting.name}?` : ''"
      confirm-label="Hapus Outlet"
      busy-label="Menghapus..."
      :busy="deleteBusy"
      @update:open="(v) => !v && (deleting = null)"
      @confirm="remove"
    >
      <p>Outlet tidak bisa dipilih lagi untuk transaksi baru. Riwayat transaksi dan stoknya tetap tersimpan. Kode outletnya tidak bisa dipakai ulang.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
