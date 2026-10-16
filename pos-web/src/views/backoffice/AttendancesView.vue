<script setup>
import { computed, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Pagination from '@/components/ui/Pagination.vue'
import Table from '@/components/ui/Table.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import http from '@/lib/http'
import { formatDateTime, formatNumber, toIsoDate } from '@/lib/format'
import { useOutletStore } from '@/stores/outlet'

/** Rekap absensi karyawan (3.7) per outlet terpilih: total jam per karyawan + rincian masuk/pulang. */
const outletStore = useOutletStore()
const weekAgo = toIsoDate(new Date(Date.now() - 6 * 86400000))
const { filters, page, setPage } = useListQuery({ from: weekAgo, to: toIsoDate(), user_id: '' })
const fetchAttendances = (params) => http.get('/attendances', { params }).then((r) => r.data)
const { rows, meta, extra, loading, error, load } = usePaginatedList(fetchAttendances, () => ({
  ...filters.value, outlet_id: outletStore.current?.id, page: page.value, per_page: 50,
}))
watch(() => outletStore.current?.id, () => load())

const summary = computed(() => extra.value.summary ?? [])
const hours = (m) => `${formatNumber(Math.floor(m / 60))} j ${m % 60} m`
const setFilter = (key, value) => (filters.value = { ...filters.value, [key]: value })
// Klik kartu karyawan → filter rincian ke karyawan itu (klik lagi untuk semua)
const selectedUser = computed(() => Number(filters.value.user_id) || null)
const toggleUser = (id) => setFilter('user_id', selectedUser.value === id ? '' : String(id))

const columns = [
  { key: 'user', label: 'Karyawan' },
  { key: 'clock_in_at', label: 'Masuk', width: '180px' },
  { key: 'clock_out_at', label: 'Pulang', width: '180px' },
  { key: 'minutes', label: 'Durasi', align: 'right', width: '120px' },
  { key: 'note', label: 'Catatan' },
]
</script>

<template>
  <BackofficeLayout>
    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-end gap-2">
        <Input :model-value="filters.from" type="date" label="Dari" class="w-40" @update:model-value="setFilter('from', $event)" />
        <Input :model-value="filters.to" type="date" label="Sampai" class="w-40" @update:model-value="setFilter('to', $event)" />
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Absensi gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
      </div>
      <template v-else>
        <div v-if="summary.length" class="flex flex-wrap gap-2" role="group" aria-label="Total per karyawan">
          <button
            v-for="s in summary"
            :key="s.user.id"
            type="button"
            :aria-pressed="selectedUser === s.user.id"
            :class="['rounded border px-3 py-2 text-left', selectedUser === s.user.id ? 'border-primary bg-primary-soft' : 'border-border bg-surface hover:bg-surface-muted']"
            @click="toggleUser(s.user.id)"
          >
            <span class="block font-medium">{{ s.user.name }}</span>
            <span class="block text-sm text-muted tabular-nums">{{ s.days }} hari · {{ hours(s.minutes) }}</span>
          </button>
        </div>

        <Table :columns="columns" :rows="rows" :loading="loading">
          <template #cell-user="{ row }">{{ row.user.name }}</template>
          <template #cell-clock_in_at="{ value }"><span class="tabular-nums">{{ formatDateTime(value) }}</span></template>
          <template #cell-clock_out_at="{ value }">
            <span v-if="value" class="tabular-nums">{{ formatDateTime(value) }}</span>
            <span v-else class="font-medium text-success">Masih bekerja</span>
          </template>
          <template #cell-minutes="{ value }"><span class="tabular-nums">{{ hours(value) }}</span></template>
          <template #cell-note="{ value }">{{ value || '–' }}</template>
          <template #empty>
            <EmptyState title="Belum ada absensi pada periode ini." description="Karyawan absen masuk otomatis saat membuka shift, atau lewat menu kasir → Absensi." />
          </template>
        </Table>
        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>
  </BackofficeLayout>
</template>
