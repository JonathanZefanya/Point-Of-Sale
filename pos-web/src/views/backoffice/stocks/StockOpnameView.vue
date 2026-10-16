<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRouter } from 'vue-router'
import OpnameApplyModal from '@/components/stocks/OpnameApplyModal.vue'
import OpnameCountTable from '@/components/stocks/OpnameCountTable.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useStockApi } from '@/composables/useStockApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatDateTime, formatNumber, formatTime } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/**
 * Hitung stok fisik (F8): scan/cari → isi jumlah fisik → tersimpan otomatis sebagai draf → Terapkan.
 * Scan barcode/SKU + Enter menambah 1 ke jumlah fisik produk tersebut.
 */
const props = defineProps({
  id: { type: String, required: true },
})

const api = useStockApi()
const toast = useToastStore()
const router = useRouter()

const opname = ref(null)
const counts = ref({})
const saved = ref({}) // nilai terakhir yang tersimpan di server
const loading = ref(true)
const error = ref('')
const saving = ref(false)
const savedAt = ref(null)
const query = ref('')
const tab = ref('all')
const highlightId = ref(null)
const modals = ref({ apply: false, cancel: false })
const busy = ref(false)
let timer = null

const isDraft = computed(() => opname.value?.status === 'draft')
const dirtyItems = computed(() => Object.entries(counts.value).filter(([id, v]) => v !== saved.value[id]).map(([id, v]) => ({ id: Number(id), counted_quantity: v })))
const countedCount = computed(() => Object.values(counts.value).filter((v) => v !== null).length)

const visibleItems = computed(() => {
  const q = query.value.trim().toLowerCase()
  return (opname.value?.items ?? []).filter((i) => {
    const counted = isDraft.value ? counts.value[i.id] : i.counted_quantity
    if (tab.value === 'uncounted' && counted !== null) return false
    if (tab.value === 'diff' && (counted === null || counted === i.system_quantity)) return false
    return !q || i.product_name.toLowerCase().includes(q) || i.sku?.toLowerCase().includes(q) || i.barcode === q
  })
})
const applyItems = computed(() => (opname.value?.items ?? []).map((i) => ({ ...i, counted: counts.value[i.id] ?? null })))

function setData(data) {
  opname.value = data
  const map = Object.fromEntries(data.items.map((i) => [i.id, i.counted_quantity]))
  counts.value = { ...map }
  saved.value = { ...map }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    setData(await api.opname(props.id))
  } catch (e) {
    error.value = e.userMessage
  } finally {
    loading.value = false
  }
}

async function save() {
  clearTimeout(timer)
  if (!dirtyItems.value.length || saving.value) return
  saving.value = true
  const sent = dirtyItems.value
  try {
    const data = await api.saveOpname(opname.value.id, sent, opname.value.note)
    sent.forEach((i) => (saved.value[i.id] = i.counted_quantity))
    opname.value = { ...data, items: data.items } // saldo sistem terbaru
    savedAt.value = new Date()
    saving.value = false
    // Perubahan yang terjadi selama menyimpan → simpan lagi
    if (dirtyItems.value.length) timer = setTimeout(save, 1500)
  } finally {
    saving.value = false
  }
}

// Simpan otomatis 1,5 detik setelah perubahan terakhir
watch(counts, () => {
  clearTimeout(timer)
  if (isDraft.value && dirtyItems.value.length) timer = setTimeout(save, 1500)
}, { deep: true })

/** Enter di kolom scan: barcode/SKU persis → +1; satu hasil nama → fokus ke inputnya. */
async function onScan() {
  const q = query.value.trim()
  if (!q || !isDraft.value) return
  const lower = q.toLowerCase()
  const exact = opname.value.items.find((i) => i.barcode === q || i.sku?.toLowerCase() === lower)
  const target = exact ?? (visibleItems.value.length === 1 ? visibleItems.value[0] : null)
  if (!target) return toast.error(`"${q}" tidak ada di daftar opname ini.`)
  if (exact) counts.value = { ...counts.value, [target.id]: (counts.value[target.id] ?? 0) + 1 }
  query.value = ''
  highlightId.value = target.id
  await nextTick()
  const row = document.getElementById(`opname-item-${target.id}`)
  row?.scrollIntoView({ block: 'center' })
  if (!exact) row?.querySelector('input')?.focus()
}

async function openApply() {
  await save()
  modals.value.apply = true
}

async function apply() {
  busy.value = true
  try {
    setData(await api.applyOpname(opname.value.id))
    modals.value.apply = false
    toast.success(`Opname ${opname.value.number} diterapkan. Stok sudah disesuaikan.`)
  } finally {
    busy.value = false
  }
}

async function cancel() {
  busy.value = true
  try {
    await api.cancelOpname(opname.value.id)
    toast.success(`Opname ${opname.value.number} dibatalkan.`)
    router.push({ name: 'stock-opnames' })
  } finally {
    busy.value = false
  }
}

const warnUnsaved = (e) => { if (dirtyItems.value.length) e.preventDefault() }
onBeforeRouteLeave(() => save())
onMounted(() => { load(); window.addEventListener('beforeunload', warnUnsaved) })
onBeforeUnmount(() => { clearTimeout(timer); window.removeEventListener('beforeunload', warnUnsaved) })

const tabs = [{ value: 'all', label: 'Semua' }, { value: 'uncounted', label: 'Belum dihitung' }, { value: 'diff', label: 'Ada selisih' }]
</script>

<template>
  <BackofficeLayout :title="opname ? `Opname ${opname.number}` : 'Stock Opname'">
    <template v-if="opname && isDraft" #header-actions>
      <span class="text-sm text-muted" aria-live="polite">{{ saving ? 'Menyimpan...' : dirtyItems.length ? 'Belum tersimpan' : savedAt ? `Tersimpan ${formatTime(savedAt)}` : 'Draf' }}</span>
      <Button variant="danger-ghost" @click="modals.cancel = true">Batalkan Opname</Button>
      <Button variant="primary" :disabled="!countedCount" @click="openApply">Tinjau & Terapkan</Button>
    </template>

    <div v-if="loading" class="flex flex-col gap-3"><Skeleton class="h-16" /><Skeleton class="h-96" /></div>
    <div v-else-if="error" class="rounded border border-border bg-surface">
      <EmptyState title="Opname gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>
    <div v-else class="flex flex-col gap-3">
      <div class="flex flex-wrap items-center gap-x-6 gap-y-1 rounded border border-border bg-surface px-4 py-3">
        <Badge :tone="isDraft ? 'warning' : opname.status === 'applied' ? 'success' : 'neutral'">{{ opname.status_label }}</Badge>
        <span>{{ opname.outlet.code }} · {{ opname.category?.name ?? 'Semua produk' }}</span>
        <span class="tabular-nums">Dihitung {{ formatNumber(isDraft ? countedCount : opname.items.filter((i) => i.counted_quantity !== null).length) }} / {{ formatNumber(opname.items.length) }}</span>
        <span v-if="opname.applied_at" class="text-muted">Diterapkan {{ formatDateTime(opname.applied_at) }} oleh {{ opname.applied_by?.name }}</span>
        <span v-if="opname.note" class="text-muted">{{ opname.note }}</span>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <input
          v-model="query"
          type="search"
          autofocus
          :placeholder="isDraft ? 'Scan barcode / ketik SKU lalu Enter (+1), atau cari nama' : 'Cari produk'"
          aria-label="Scan atau cari produk"
          class="h-10 w-96 max-w-full rounded border border-border-strong bg-surface px-3 focus:border-primary focus:outline-none"
          @keydown.enter.prevent="onScan"
        >
        <div class="inline-flex rounded border border-border-strong bg-surface p-0.5" role="tablist" aria-label="Filter daftar">
          <button
            v-for="t in tabs"
            :key="t.value"
            type="button"
            role="tab"
            :aria-selected="tab === t.value"
            :class="['rounded-sm px-3 py-1.5', tab === t.value ? 'bg-primary-soft font-medium text-primary' : 'hover:bg-surface-muted']"
            @click="tab = t.value"
          >
            {{ t.label }}
          </button>
        </div>
      </div>
      <p v-if="isDraft" class="text-sm text-muted">Stok saat ini mengikuti penjualan yang terjadi selama menghitung. Kosongkan jumlah fisik untuk produk yang tidak dihitung.</p>

      <OpnameCountTable v-model:counts="counts" :items="visibleItems" :readonly="!isDraft" :highlight-id="highlightId" />
    </div>

    <OpnameApplyModal v-if="opname" v-model:open="modals.apply" :items="applyItems" :busy="busy" @confirm="apply" />
    <ConfirmModal v-if="opname" v-model:open="modals.cancel" :title="`Batalkan opname ${opname.number}?`" confirm-label="Batalkan Opname" busy-label="Membatalkan..." :busy="busy" @confirm="cancel">
      <p>Jumlah fisik yang sudah diisi dibuang. Stok tidak berubah.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
