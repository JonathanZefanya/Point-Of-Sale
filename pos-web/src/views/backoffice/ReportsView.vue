<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PeriodPicker from '@/components/reports/PeriodPicker.vue'
import DetailReportTable from '@/components/reports/DetailReportTable.vue'
import ReportSummary from '@/components/reports/ReportSummary.vue'
import SalesReportTable from '@/components/reports/SalesReportTable.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useReportApi } from '@/composables/useReportApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import Select from '@/components/ui/Select.vue'
import { formatNumber, formatRupiah } from '@/lib/format'
import { rangeFor } from '@/lib/periods'
import { REPORT_DEFS, REPORTS } from '@/lib/reports'
import { useOutletStore } from '@/stores/outlet'

/** Laporan (F11): pilih jenis → filter default hari ini + outlet sendiri → ringkasan, tabel, Export Excel. */
const api = useReportApi()
const outletStore = useOutletStore()
const route = useRoute()
const router = useRouter()

const report = computed({
  get: () => (REPORTS.some((r) => r.value === route.query.report) ? route.query.report : 'sales'),
  set: (value) => router.replace({ query: { ...route.query, report: value } }),
})
const period = computed({
  get: () => {
    const preset = route.query.preset ?? 'today'
    return preset === 'custom' ? { preset, from: route.query.from, to: route.query.to } : { preset, ...rangeFor(preset) }
  },
  set: (value) => router.replace({ query: { ...route.query, ...value } }),
})

const rows = ref([])
const summary = ref(null)
const loading = ref(true)
const error = ref('')
const exporting = ref(false)

const params = computed(() => ({ outlet_id: outletStore.current?.id, from: period.value.from, to: period.value.to }))

const summaryItems = computed(() => {
  if (!summary.value) return []
  if (REPORT_DEFS[report.value]) return REPORT_DEFS[report.value].summary(summary.value)
  const s = summary.value
  return [
    { label: 'Penjualan bersih', value: s.net, note: s.refunds ? `Sudah dikurangi retur ${formatRupiah(s.refunds)}` : '' },
    { label: 'Jumlah transaksi', value: s.transactions, money: false },
    { label: 'Rata-rata per transaksi', value: s.average },
    { label: 'Transaksi dibatalkan', value: s.void_amount, note: `${formatNumber(s.void_count)} transaksi, tidak dihitung di omzet` },
  ]
})

const definition = computed(() => REPORT_DEFS[report.value] ?? null)

let requestId = 0
async function load() {
  const id = ++requestId
  loading.value = true
  error.value = ''
  try {
    const result = report.value === 'sales' ? await api.sales(params.value) : await api.detail(report.value, params.value)
    if (id !== requestId) return
    rows.value = result.data
    summary.value = result.summary
  } catch (e) {
    if (id === requestId) error.value = e.userMessage
  } finally {
    if (id === requestId) loading.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  try {
    await api.download(report.value, params.value)
  } finally {
    exporting.value = false
  }
}

watch([report, () => period.value.from, () => period.value.to, () => outletStore.current?.id], load)
onMounted(async () => {
  await outletStore.ensureLoaded().catch(() => {})
  load()
})
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" :loading="exporting" loading-text="Menyiapkan..." :disabled="loading || !rows.length" @click="exportExcel">Export Excel</Button>
    </template>

    <div class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <Select v-model="report" :options="REPORTS" aria-label="Jenis laporan" class="w-48" />
        <PeriodPicker v-model="period" />
      </div>

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Laporan gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
      </div>

      <template v-else>
        <ReportSummary v-if="summaryItems.length" :items="summaryItems" />

        <SalesReportTable v-if="report === 'sales'" :rows="rows" :loading="loading">
          <template #empty>
            <EmptyState title="Belum ada penjualan pada periode ini." description="Pilih periode lain, mis. 7 hari atau Bulan ini." />
          </template>
        </SalesReportTable>

        <template v-else-if="definition">
          <p v-if="definition.note" class="text-muted">{{ definition.note }}</p>
          <DetailReportTable :definition="definition" :rows="rows" :loading="loading">
            <template #empty>
              <EmptyState :title="definition.empty" description="Pilih periode lain, mis. 7 hari atau Bulan ini." />
            </template>
          </DetailReportTable>
        </template>
      </template>
    </div>
  </BackofficeLayout>
</template>
