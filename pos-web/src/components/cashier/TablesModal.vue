<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useSaleApi } from '@/composables/useSaleApi'
import { useTableApi } from '@/composables/useTableApi'
import { formatRupiah, formatTime } from '@/lib/format'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

/**
 * Denah meja di kasir (F&B 3.3). Meja kosong → pakai untuk keranjang ini; meja terisi → buka bill-nya.
 * Saat bill meja sedang dibuka: mode Pindah (ke meja kosong) atau Gabung (ke bill meja lain).
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  outletId: { type: Number, required: true },
})

const emit = defineEmits(['changed'])

const cart = useCartStore()
const toast = useToastStore()
const api = useTableApi()
const saleApi = useSaleApi()
const tables = ref([])
const loading = ref(false)
const busy = ref(false)
const mode = ref('pick') // pick | move | merge

const isTableBill = computed(() => !!cart.heldSaleId && !!cart.table)
const areas = computed(() => [...new Set(tables.value.map((t) => t.area || 'Tanpa area'))])
const inArea = (area) => tables.value.filter((t) => (t.area || 'Tanpa area') === area)

async function load() {
  loading.value = true
  try {
    tables.value = await api.list(props.outletId, true)
  } finally {
    loading.value = false
  }
}

watch(open, (isOpen) => {
  if (!isOpen) return
  mode.value = 'pick'
  load()
})

const hint = computed(() => ({
  pick: 'Pilih meja kosong untuk keranjang ini, atau buka bill meja yang terisi.',
  move: `Pilih meja kosong sebagai tujuan pindah bill ${cart.table?.name}.`,
  merge: `Pilih meja terisi; bill ${cart.table?.name} digabung ke bill meja itu.`,
}[mode.value]))

function disabled(t) {
  if (mode.value === 'move') return t.occupied
  if (mode.value === 'merge') return !t.occupied || t.open_sale.id === cart.heldSaleId
  return t.occupied && t.open_sale.id === cart.heldSaleId
}

async function select(t) {
  if (disabled(t) || busy.value) return
  busy.value = true
  try {
    if (mode.value === 'move') {
      const sale = await api.move(cart.heldSaleId, t.id)
      cart.setTable(sale.table)
      toast.success(`Bill dipindah ke ${t.name}.`)
    } else if (mode.value === 'merge') {
      const sale = await api.merge(cart.heldSaleId, t.open_sale.id)
      cart.loadHeld(sale)
      toast.success(`Bill digabung ke ${t.name}.`)
    } else if (t.occupied) {
      if (!cart.isEmpty && !cart.heldSaleId) {
        toast.error('Keranjang masih berisi. Simpan (F4) atau selesaikan dulu sebelum membuka bill meja lain.')
        return
      }
      cart.loadHeld(await saleApi.get(t.open_sale.id))
      toast.info(`Membuka bill ${t.name}.`)
    } else {
      cart.setTable({ id: t.id, name: t.name })
      toast.info(`Keranjang untuk ${t.name}. Tekan Simpan (F4) untuk membuka bill, atau Bayar.`)
    }
    open.value = false
    emit('changed')
  } finally {
    busy.value = false
  }
}

const tile = (t) => [
  'flex min-h-[88px] flex-col items-start justify-between rounded border p-2 text-left',
  t.occupied ? 'border-primary bg-primary-soft' : 'border-border-strong bg-surface',
  disabled(t) ? 'cursor-not-allowed opacity-40' : 'hover:border-primary',
]
</script>

<template>
  <Modal v-model:open="open" title="Meja" size="lg" :dismissible="!busy">
    <div class="cashier-root flex flex-col gap-3">
      <div v-if="isTableBill" class="flex flex-wrap items-center gap-2">
        <span class="text-muted">Bill {{ cart.table.name }}:</span>
        <Button size="lg" :variant="mode === 'move' ? 'primary' : 'secondary'" @click="mode = mode === 'move' ? 'pick' : 'move'">Pindah Meja</Button>
        <Button size="lg" :variant="mode === 'merge' ? 'primary' : 'secondary'" @click="mode = mode === 'merge' ? 'pick' : 'merge'">Gabung Bill</Button>
      </div>
      <p class="text-muted">{{ hint }}</p>

      <div v-if="loading" class="grid grid-cols-4 gap-2"><Skeleton v-for="n in 8" :key="n" class="h-[88px]" /></div>
      <p v-else-if="!tables.length" class="py-6 text-center text-muted">Belum ada meja di outlet ini. Pemilik/manajer bisa menambahkannya di back office → Meja.</p>
      <section v-for="area in areas" v-else :key="area" class="flex flex-col gap-2">
        <h3 v-if="areas.length > 1" class="font-semibold">{{ area }}</h3>
        <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
          <button v-for="t in inArea(area)" :key="t.id" type="button" :class="tile(t)" :disabled="disabled(t)" @click="select(t)">
            <span class="font-semibold">{{ t.name }}</span>
            <template v-if="t.occupied">
              <span class="text-sm tabular-nums">{{ formatRupiah(t.open_sale.grand_total) }}</span>
              <span class="text-xs text-muted">{{ t.open_sale.items_count }} produk · sejak {{ formatTime(t.open_sale.opened_at) }}</span>
            </template>
            <span v-else class="text-sm text-muted">Kosong · {{ t.capacity }} kursi</span>
          </button>
        </div>
      </section>
    </div>
    <template #footer>
      <Button size="lg" :disabled="busy" @click="open = false">Tutup</Button>
    </template>
  </Modal>
</template>
