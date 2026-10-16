<script setup>
import { onMounted, ref } from 'vue'
import PromotionFormModal from '@/components/promotions/PromotionFormModal.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Table from '@/components/ui/Table.vue'
import { useProductApi } from '@/composables/useProductApi'
import { usePromotionApi } from '@/composables/usePromotionApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatNumber } from '@/lib/format'
import { describeSchedule, describeValue } from '@/lib/promotions'
import { useOutletStore } from '@/stores/outlet'
import { useToastStore } from '@/stores/toast'

/** Promo & voucher (2.5): daftar, tambah/ubah, hapus. Promo berjalan otomatis di kasir sesuai aturannya. */
const api = usePromotionApi()
const productApi = useProductApi()
const outletStore = useOutletStore()
const toast = useToastStore()

const rows = ref([])
const loading = ref(true)
const error = ref('')
const products = ref([])
const categories = ref([])

const selected = ref(null)
const formOpen = ref(false)
const deleting = ref(null)
const deleteBusy = ref(false)

const columns = [
  { key: 'name', label: 'Promo' },
  { key: 'value', label: 'Nilai' },
  { key: 'schedule', label: 'Berlaku' },
  { key: 'voucher_code', label: 'Voucher', width: '170px' },
  { key: 'is_active', label: 'Status', width: '110px' },
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    rows.value = await api.list()
  } catch (e) {
    error.value = e.userMessage
  } finally {
    loading.value = false
  }
}

function openForm(promo = null) {
  selected.value = promo
  formOpen.value = true
}

async function remove() {
  deleteBusy.value = true
  try {
    await api.remove(deleting.value.id)
    toast.success(`Promo ${deleting.value.name} dihapus.`)
    deleting.value = null
    load()
  } finally {
    deleteBusy.value = false
  }
}

onMounted(() => {
  load()
  // Pilihan produk/kategori/outlet untuk form
  productApi.list({ per_page: 200, is_active: 1 }).then((r) => (products.value = r.data)).catch(() => {})
  productApi.categories().then((c) => (categories.value = c)).catch(() => {})
  outletStore.ensureLoaded().catch(() => {})
})
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" @click="openForm()">Tambah Promo</Button>
    </template>

    <div v-if="error" class="rounded border border-border bg-surface">
      <EmptyState title="Daftar promo gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>
    <Table v-else :columns="columns" :rows="rows" :loading="loading" clickable @row-click="openForm">
      <template #cell-name="{ row }">
        <span class="block font-medium">{{ row.name }}</span>
        <span class="block text-muted">{{ row.type_label }}</span>
      </template>
      <template #cell-value="{ row }">{{ describeValue(row) }}</template>
      <template #cell-schedule="{ row }">{{ describeSchedule(row) }}</template>
      <template #cell-voucher_code="{ row }">
        <template v-if="row.voucher_code">
          <span class="block font-mono">{{ row.voucher_code }}</span>
          <span class="block text-muted tabular-nums">
            Terpakai {{ formatNumber(row.voucher_used) }}<template v-if="row.voucher_quota"> / {{ formatNumber(row.voucher_quota) }}</template>
          </span>
        </template>
        <span v-else class="text-muted">Otomatis</span>
      </template>
      <template #cell-is_active="{ value }">
        <Badge :tone="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Nonaktif' }}</Badge>
      </template>
      <template #empty>
        <EmptyState title="Belum ada promo." description="Buat diskon produk, diskon transaksi, beli X gratis Y, happy hour, atau voucher.">
          <Button variant="primary" @click="openForm()">Tambah Promo</Button>
        </EmptyState>
      </template>
    </Table>

    <PromotionFormModal
      v-model:open="formOpen"
      :promotion="selected"
      :products="products"
      :categories="categories"
      :outlets="outletStore.outlets"
      @saved="load"
      @delete="(p) => { formOpen = false; deleting = p }"
    />
    <ConfirmModal
      :open="!!deleting"
      :title="deleting ? `Hapus promo ${deleting.name}?` : ''"
      confirm-label="Hapus Promo"
      busy-label="Menghapus..."
      :busy="deleteBusy"
      @update:open="(v) => !v && (deleting = null)"
      @confirm="remove"
    >
      <p>Promo tidak lagi berlaku di kasir dan kode vouchernya tidak bisa dipakai. Transaksi lama tetap menyimpan rincian promonya.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
