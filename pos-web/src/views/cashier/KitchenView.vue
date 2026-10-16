<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import KitchenTicketCard from '@/components/kitchen/KitchenTicketCard.vue'
import Button from '@/components/ui/Button.vue'
import CashierLayout from '@/layouts/CashierLayout.vue'
import http from '@/lib/http'
import { formatTime } from '@/lib/format'
import { readStorage, writeStorage } from '@/lib/storage'
import { useAuthStore } from '@/stores/auth'
import { useDeviceStore } from '@/stores/device'
import { useOutletStore } from '@/stores/outlet'

/**
 * Kitchen Display (3.4): tiket dari kasir per kolom Baru / Diproses / Siap, diperbarui tiap 5 detik.
 * "Cetak otomatis" (per perangkat) mencetak tiket baru ke printer yang terpasang di perangkat dapur.
 */
const AUTO_PRINT_KEY = 'pos.kitchen.auto_print'
const auth = useAuthStore()
const device = useDeviceStore()
const outletStore = useOutletStore()
const router = useRouter()

const outlet = ref(null)
const tickets = ref([])
const error = ref('')
const loadedAt = ref(null)
const now = ref(Date.now())
const busyId = ref(null)
const printing = ref(null)
const autoPrint = ref(readStorage(AUTO_PRINT_KEY) === '1')
const seen = new Set()
let timer = null
let firstLoad = true

const columns = computed(() => [
  { status: 'new', label: 'Baru', items: tickets.value.filter((t) => t.status === 'new') },
  { status: 'preparing', label: 'Diproses', items: tickets.value.filter((t) => t.status === 'preparing') },
  { status: 'ready', label: 'Siap diantar', items: tickets.value.filter((t) => t.status === 'ready') },
])

async function load() {
  now.value = Date.now()
  if (!outlet.value) return
  try {
    const { data } = await http.get('/kitchen/tickets', { params: { outlet_id: outlet.value.id }, silent: true })
    const fresh = data.data.filter((t) => !seen.has(t.id))
    fresh.forEach((t) => seen.add(t.id))
    tickets.value = data.data
    loadedAt.value = new Date()
    error.value = ''
    // Tiket yang sudah ada saat layar dibuka tidak dicetak ulang
    if (autoPrint.value && !firstLoad) for (const t of fresh) await print(t)
    firstLoad = false
  } catch (e) {
    error.value = e.userMessage
  }
}

async function advance(ticket, status) {
  busyId.value = ticket.id
  try {
    await http.post(`/kitchen/tickets/${ticket.id}/status`, { status })
    await load()
  } finally {
    busyId.value = null
  }
}

/** Cetak tiket 58 mm lewat window.print() (area cetak di bawah). */
async function print(ticket) {
  printing.value = ticket
  await nextTick()
  window.print()
  printing.value = null
}

function toggleAutoPrint() {
  autoPrint.value = !autoPrint.value
  writeStorage(AUTO_PRINT_KEY, autoPrint.value ? '1' : '0')
}

onMounted(async () => {
  await outletStore.ensureLoaded().catch(() => {})
  outlet.value = outletStore.outlets.find((o) => o.code === device.outletCode) ?? outletStore.current
  await load()
  timer = setInterval(load, 5000)
})
onBeforeUnmount(() => clearInterval(timer))
</script>

<template>
  <CashierLayout :outlet-code="outlet?.code ?? ''" :cashier-name="auth.user?.name ?? ''">
    <template #header-actions>
      <span v-if="loadedAt" class="text-sm text-muted">Diperbarui {{ formatTime(loadedAt) }}</span>
      <Button size="lg" :variant="autoPrint ? 'primary' : 'secondary'" :aria-pressed="autoPrint" @click="toggleAutoPrint">
        Cetak Otomatis: {{ autoPrint ? 'Aktif' : 'Mati' }}
      </Button>
      <Button size="lg" @click="router.push({ name: 'cashier' })">Ke Kasir</Button>
    </template>

    <div class="flex min-h-0 flex-1 flex-col gap-3 p-4">
      <p v-if="error" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ error }} Mencoba lagi otomatis.</p>
      <div class="grid min-h-0 flex-1 grid-cols-3 gap-4">
        <section v-for="col in columns" :key="col.status" class="flex min-h-0 flex-col gap-2">
          <h2 class="font-semibold">{{ col.label }} <span class="tabular-nums text-muted">({{ col.items.length }})</span></h2>
          <div class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto">
            <KitchenTicketCard
              v-for="t in col.items"
              :key="t.id"
              :ticket="t"
              :now="now"
              :busy="busyId === t.id"
              @advance="(s) => advance(t, s)"
              @print="print(t)"
            />
            <p v-if="!col.items.length" class="rounded border border-dashed border-border p-4 text-center text-muted">
              {{ col.status === 'new' ? 'Belum ada pesanan baru. Pesanan muncul otomatis saat kasir menyimpan atau membayar bill.' : 'Kosong' }}
            </p>
          </div>
        </section>
      </div>
    </div>

    <!-- Area cetak tiket dapur: tidak terlihat di layar -->
    <div v-if="printing" class="print-only print-area">
      <p class="text-lg font-bold">{{ printing.label }}</p>
      <p>{{ printing.order_type_label ?? 'Pesanan' }}<span v-if="printing.is_addition"> · TAMBAHAN</span> · {{ formatTime(printing.created_at) }}</p>
      <hr class="my-1 border-black">
      <div v-for="(item, i) in printing.items" :key="i" class="mb-1">
        <p class="text-base font-semibold">{{ item.quantity }} × {{ item.name }}</p>
        <p v-if="item.modifiers?.length">{{ item.modifiers.join(', ') }}</p>
        <p v-if="item.note">* {{ item.note }}</p>
      </div>
    </div>
  </CashierLayout>
</template>
