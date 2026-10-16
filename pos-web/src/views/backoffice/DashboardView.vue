<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import DashboardLists from '@/components/dashboard/DashboardLists.vue'
import HourlyChart from '@/components/dashboard/HourlyChart.vue'
import StatTile from '@/components/dashboard/StatTile.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useReportApi } from '@/composables/useReportApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatDate, toIsoDate } from '@/lib/format'
import { useOutletStore } from '@/stores/outlet'

/** Dashboard (FE-1.12): semua angka dengan pembanding yang tertulis jelas. Tanpa banner sambutan. */
const api = useReportApi()
const outletStore = useOutletStore()

const date = ref(toIsoDate())
const data = ref(null)
const loading = ref(true)
const error = ref('')

const isToday = computed(() => date.value === toIsoDate())
const currentLabel = computed(() => (isToday.value ? 'Hari ini' : formatDate(date.value)))
const compareLabel = computed(() => (isToday.value ? 'kemarin' : 'hari sebelumnya'))

let requestId = 0
async function load() {
  const id = ++requestId
  loading.value = true
  error.value = ''
  try {
    const result = await api.dashboard({ outlet_id: outletStore.current?.id, date: date.value })
    if (id === requestId) data.value = result
  } catch (e) {
    if (id === requestId) error.value = e.userMessage
  } finally {
    if (id === requestId) loading.value = false
  }
}

watch([date, () => outletStore.current?.id], load)
onMounted(async () => {
  await outletStore.ensureLoaded().catch(() => {})
  load()
})
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Input v-model="date" type="date" :max="toIsoDate()" aria-label="Tanggal dashboard" class="w-40" />
    </template>

    <div v-if="error" class="max-w-xl rounded border border-border bg-surface">
      <EmptyState title="Dashboard gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>

    <div v-else-if="loading && !data" class="grid gap-3" aria-busy="true">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><Skeleton v-for="n in 4" :key="n" height="6rem" /></div>
      <Skeleton height="16rem" />
    </div>

    <div v-else-if="data" :class="['grid gap-3', loading && 'opacity-60']">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatTile :label="`Omzet ${currentLabel.toLowerCase()}`" :value="data.current.revenue" :previous="data.previous.revenue" :compare-label="compareLabel" />
        <StatTile label="Jumlah transaksi" :value="data.current.transactions" :previous="data.previous.transactions" :money="false" :compare-label="compareLabel" />
        <StatTile label="Rata-rata per transaksi" :value="data.current.average" :previous="data.previous.average" :compare-label="compareLabel" />
        <StatTile label="Laba kotor" :value="data.current.gross_profit" :previous="data.previous.gross_profit" :compare-label="compareLabel" />
      </div>

      <section class="rounded border border-border bg-surface px-4 py-3">
        <h2 class="mb-2 font-semibold">Omzet per jam · {{ formatDate(data.date) }} vs {{ formatDate(data.compare_date) }}</h2>
        <HourlyChart :current="data.hourly" :previous="data.hourly_previous" :current-label="currentLabel" :previous-label="formatDate(data.compare_date)" />
      </section>

      <DashboardLists :top-products="data.top_products" :payments="data.payments" :stock="data.stock" />
    </div>
  </BackofficeLayout>
</template>
