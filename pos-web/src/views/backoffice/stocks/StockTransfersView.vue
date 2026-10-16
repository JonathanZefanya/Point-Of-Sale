<script setup>
import { computed, watch } from 'vue'
import { useRouter } from 'vue-router'
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

/** Transfer stok (F10) untuk outlet terpilih: Masuk (perlu diterima) / Keluar. */
const api = useStockApi()
const outletStore = useOutletStore()
const router = useRouter()

const { filters, page, setPage } = useListQuery({ direction: 'in' })
const outletId = computed(() => outletStore.current?.id)
const { rows, meta, loading, error, load } = usePaginatedList(api.transfers, () => ({ outlet_id: outletId.value, direction: filters.value.direction, page: page.value, per_page: 20 }))
watch(outletId, () => load())

const tones = { in_transit: 'warning', received: 'success', cancelled: 'neutral' }
const tabs = [{ value: 'in', label: 'Masuk' }, { value: 'out', label: 'Keluar' }]

const columns = computed(() => [
  { key: 'number', label: 'No. transfer', width: '230px' },
  { key: 'outlet', label: filters.value.direction === 'in' ? 'Dari outlet' : 'Ke outlet' },
  { key: 'total_quantity', label: 'Jumlah unit', align: 'right', width: '120px' },
  { key: 'created_at', label: 'Dikirim', width: '180px' },
  { key: 'status', label: 'Status', width: '170px' },
])
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" :disabled="!outletStore.hasMany && outletStore.loaded" @click="router.push({ name: 'stock-transfer-new' })">Buat Transfer</Button>
    </template>

    <div class="flex flex-col gap-3">
      <div class="inline-flex self-start rounded border border-border-strong bg-surface p-0.5" role="tablist" aria-label="Arah transfer">
        <button
          v-for="t in tabs"
          :key="t.value"
          type="button"
          role="tab"
          :aria-selected="filters.direction === t.value"
          :class="['rounded-sm px-3 py-1.5', filters.direction === t.value ? 'bg-primary-soft font-medium text-primary' : 'hover:bg-surface-muted']"
          @click="filters = { ...filters, direction: t.value }"
        >
          {{ t.label }}
        </button>
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Daftar transfer gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
      </div>
      <template v-else>
        <Table :columns="columns" :rows="rows" :loading="loading" clickable @row-click="(row) => router.push({ name: 'stock-transfer', params: { id: row.id } })">
          <template #cell-number="{ value }"><span class="font-mono">{{ value }}</span></template>
          <template #cell-outlet="{ row }">
            <template v-if="filters.direction === 'in'">{{ row.from_outlet.code }} · {{ row.from_outlet.name }}</template>
            <template v-else>{{ row.to_outlet.code }} · {{ row.to_outlet.name }}</template>
          </template>
          <template #cell-total_quantity="{ value }">{{ formatNumber(value) }}</template>
          <template #cell-created_at="{ value }">{{ formatDateTime(value) }}</template>
          <template #cell-status="{ row }">
            <Badge :tone="tones[row.status]">{{ row.status === 'in_transit' && filters.direction === 'in' ? 'Perlu diterima' : row.status_label }}</Badge>
          </template>
          <template #empty>
            <EmptyState v-if="outletStore.loaded && !outletStore.hasMany" title="Transfer stok butuh minimal 2 outlet." description="Pemilik bisa menambah outlet di menu Outlet." />
            <EmptyState
              v-else-if="filters.direction === 'in'"
              title="Belum ada transfer masuk."
              description="Transfer dari outlet lain ke outlet ini muncul di sini untuk diterima."
            />
            <EmptyState v-else title="Belum ada transfer keluar." description="Kirim stok dari outlet ini ke outlet lain.">
              <Button variant="primary" @click="router.push({ name: 'stock-transfer-new' })">Buat Transfer</Button>
            </EmptyState>
          </template>
        </Table>
        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>
  </BackofficeLayout>
</template>
