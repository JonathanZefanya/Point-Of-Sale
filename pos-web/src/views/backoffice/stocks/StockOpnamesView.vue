<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import StartOpnameModal from '@/components/stocks/StartOpnameModal.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Table from '@/components/ui/Table.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import { useStockApi } from '@/composables/useStockApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatDateTime, formatNumber } from '@/lib/format'
import { useOutletStore } from '@/stores/outlet'

/** Daftar stock opname (F8) per outlet: draf yang bisa dilanjutkan dan riwayat yang sudah diterapkan. */
const api = useStockApi()
const outletStore = useOutletStore()
const router = useRouter()

const { page, setPage } = useListQuery({})
const { rows, meta, loading, error, load } = usePaginatedList(api.opnames, () => ({ outlet_id: outletStore.current?.id, page: page.value, per_page: 20 }))
watch(() => outletStore.current?.id, () => load())

const startOpen = ref(false)
const tones = { draft: 'warning', applied: 'success', cancelled: 'neutral' }

const columns = [
  { key: 'number', label: 'No. opname', width: '230px' },
  { key: 'category', label: 'Produk' },
  { key: 'progress', label: 'Dihitung', align: 'right', width: '130px' },
  { key: 'updated_at', label: 'Terakhir diubah', width: '180px' },
  { key: 'status', label: 'Status', width: '130px' },
]
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" @click="startOpen = true">Mulai Opname</Button>
    </template>

    <div v-if="error" class="rounded border border-border bg-surface">
      <EmptyState title="Daftar opname gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>
    <template v-else>
      <Table :columns="columns" :rows="rows" :loading="loading" clickable @row-click="(row) => router.push({ name: 'stock-opname', params: { id: row.id } })">
        <template #cell-number="{ value }"><span class="font-mono">{{ value }}</span></template>
        <template #cell-category="{ row }">
          {{ row.category?.name ?? 'Semua produk' }}
          <span v-if="row.note" class="block text-muted">{{ row.note }}</span>
        </template>
        <template #cell-progress="{ row }">{{ formatNumber(row.counted_count) }} / {{ formatNumber(row.items_count) }}</template>
        <template #cell-updated_at="{ value }">{{ formatDateTime(value) }}</template>
        <template #cell-status="{ row }"><Badge :tone="tones[row.status]">{{ row.status_label }}</Badge></template>
        <template #empty>
          <EmptyState title="Belum ada stock opname." description="Hitung stok fisik lalu samakan dengan stok di sistem. Bisa disimpan sebagai draf dan dilanjutkan nanti.">
            <Button variant="primary" @click="startOpen = true">Mulai Opname</Button>
          </EmptyState>
        </template>
      </Table>
      <Pagination :meta="meta" :disabled="loading" @change="setPage" />
    </template>

    <StartOpnameModal v-model:open="startOpen" :outlet="outletStore.current" />
  </BackofficeLayout>
</template>
