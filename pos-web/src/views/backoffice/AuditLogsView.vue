<script setup>
import { computed, onMounted, ref } from 'vue'
import AuditLogDetailModal from '@/components/audit/AuditLogDetailModal.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Select from '@/components/ui/Select.vue'
import Table from '@/components/ui/Table.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatDateTime } from '@/lib/format'
import http from '@/lib/http'

/** Audit log (2.12): aksi sensitif — void, refund, diskon manual, ubah harga, stok, kas keluar. Hanya baca. */
const fetchLogs = (params) => http.get('/audit-logs', { params }).then((r) => r.data)

const { filters, page, hasFilter, setPage, resetFilters } = useListQuery({ user_id: '', action: '', from: '', to: '' })
const { rows, meta, loading, error, load } = usePaginatedList(fetchLogs, () => ({ ...filters.value, page: page.value, per_page: 50 }))

const options = ref({ actions: [], users: [] })
const selected = ref(null)
const detailOpen = ref(false)

const userOptions = computed(() => [{ value: '', label: 'Semua pengguna' }, ...options.value.users.map((u) => ({ value: String(u.id), label: u.name }))])
const actionOptions = computed(() => [{ value: '', label: 'Semua aksi' }, ...options.value.actions])

const setFilter = (key, value) => (filters.value = { ...filters.value, [key]: value ?? '' })

const columns = [
  { key: 'created_at', label: 'Waktu', width: '180px' },
  { key: 'user', label: 'Pengguna', width: '180px' },
  { key: 'action_label', label: 'Aksi', width: '240px' },
  { key: 'subject', label: 'Data' },
]

function openDetail(log) {
  selected.value = log
  detailOpen.value = true
}

onMounted(() => http.get('/audit-logs/filters').then((r) => (options.value = r.data.data)).catch(() => {}))
</script>

<template>
  <BackofficeLayout>
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <Select :model-value="filters.user_id" :options="userOptions" aria-label="Filter pengguna" class="w-48" @update:model-value="setFilter('user_id', $event)" />
        <Select :model-value="filters.action" :options="actionOptions" aria-label="Filter aksi" class="w-60" @update:model-value="setFilter('action', $event)" />
        <Input :model-value="filters.from" type="date" label="Dari" class="w-40" @update:model-value="setFilter('from', $event)" />
        <Input :model-value="filters.to" type="date" label="Sampai" class="w-40" @update:model-value="setFilter('to', $event)" />
        <Button v-if="hasFilter" variant="ghost" @click="resetFilters">Hapus filter</Button>
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Audit log gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
      </div>
      <template v-else>
        <Table :columns="columns" :rows="rows" :loading="loading" clickable @row-click="openDetail">
          <template #cell-created_at="{ value }"><span class="tabular-nums">{{ formatDateTime(value) }}</span></template>
          <template #cell-user="{ row }">{{ row.user?.name ?? 'Sistem' }}</template>
          <template #cell-subject="{ value }">{{ value ?? '–' }}</template>
          <template #empty>
            <EmptyState v-if="hasFilter" title="Tidak ada log yang cocok dengan filter." description="Ubah filter atau perlebar rentang tanggal.">
              <Button @click="resetFilters">Hapus Filter</Button>
            </EmptyState>
            <EmptyState v-else title="Belum ada aktivitas tercatat." description="Void, refund, diskon manual, ubah harga, penyesuaian stok, dan kas keluar akan muncul di sini." />
          </template>
        </Table>
        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>

    <AuditLogDetailModal v-model:open="detailOpen" :log="selected" />
  </BackofficeLayout>
</template>
