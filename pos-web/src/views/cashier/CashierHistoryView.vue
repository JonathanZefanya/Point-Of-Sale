<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import WhatsAppShareButton from '@/components/receipt/WhatsAppShareButton.vue'
import SaleDetail from '@/components/sales/SaleDetail.vue'
import RefundModal from '@/components/sales/RefundModal.vue'
import VoidSaleModal from '@/components/sales/VoidSaleModal.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Modal from '@/components/ui/Modal.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { usePrint } from '@/composables/usePrint'
import { useSaleApi } from '@/composables/useSaleApi'
import CashierLayout from '@/layouts/CashierLayout.vue'
import { formatRupiah, formatTime } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { useDeviceStore } from '@/stores/device'
import { useShiftStore } from '@/stores/shift'
import { useToastStore } from '@/stores/toast'

/** Riwayat transaksi shift ini + batalkan (FE-1.10, F5). */
const api = useSaleApi()
const auth = useAuthStore()
const shiftStore = useShiftStore()
const toast = useToastStore()
const router = useRouter()
const { printSale } = usePrint()

const sales = ref([])
const loading = ref(true)
const error = ref('')
const selected = ref(null)
const detailOpen = ref(false)
const voidOpen = ref(false)
const refundOpen = ref(false)
const search = ref('')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? sales.value.filter((s) => s.number.toLowerCase().includes(q)) : sales.value
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const result = await api.list({ shift_id: shiftStore.shift.id, per_page: 100 })
    sales.value = result.data
  } catch (e) {
    error.value = e.userMessage
  } finally {
    loading.value = false
  }
}

async function open(sale) {
  selected.value = await api.get(sale.id)
  detailOpen.value = true
}

function onRefunded(sale) {
  toast.success(`Refund ${sale.number} diproses.`)
  selected.value = sale
  load()
}

function onVoided(sale) {
  toast.success(`Transaksi ${sale.number} dibatalkan. Stok sudah dikembalikan.`)
  selected.value = sale
  load()
}

onMounted(load)
</script>

<template>
  <CashierLayout :outlet-code="useDeviceStore().outletCode ?? ''" :cashier-name="auth.user?.name ?? ''" :shift-opened-at="shiftStore.shift?.opened_at">
    <template #header-actions>
      <Button size="lg" variant="primary" @click="router.push({ name: 'cashier' })">Kembali ke Kasir</Button>
    </template>

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-3 overflow-y-auto p-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Transaksi shift ini</h1>
        <input v-model="search" type="search" placeholder="Cari nomor transaksi" aria-label="Cari nomor transaksi" class="min-h-touch w-64 rounded border border-border-strong bg-surface px-3 focus:border-primary focus:outline-none">
      </div>

      <div v-if="loading" class="flex flex-col gap-2" aria-busy="true">
        <Skeleton v-for="n in 5" :key="n" height="3.5rem" />
      </div>
      <div v-else-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Riwayat gagal dimuat." :description="error"><Button size="lg" variant="primary" @click="load">Coba Lagi</Button></EmptyState>
      </div>
      <div v-else-if="!filtered.length" class="rounded border border-border bg-surface">
        <EmptyState :title="search ? 'Nomor transaksi tidak ditemukan.' : 'Belum ada transaksi di shift ini.'" description="Transaksi yang selesai di shift ini akan muncul di sini.">
          <Button size="lg" variant="primary" @click="router.push({ name: 'cashier' })">Kembali ke Kasir</Button>
        </EmptyState>
      </div>
      <ul v-else class="divide-y divide-border rounded border border-border bg-surface">
        <li v-for="sale in filtered" :key="sale.id">
          <button type="button" class="flex min-h-16 w-full items-center gap-3 px-4 py-2 text-left hover:bg-surface-muted" @click="open(sale)">
            <span class="w-14 tabular-nums text-muted">{{ formatTime(sale.completed_at) }}</span>
            <span class="flex-1 font-mono">{{ sale.number }}</span>
            <Badge v-if="sale.status !== 'completed'" tone="danger">{{ sale.status_label }}</Badge>
            <span :class="['num w-32 font-semibold', sale.status === 'void' && 'text-muted line-through']">{{ formatRupiah(sale.grand_total) }}</span>
          </button>
        </li>
      </ul>
    </div>

    <Modal v-model:open="detailOpen" title="Rincian transaksi" size="md">
      <div class="cashier-root"><SaleDetail v-if="selected" :sale="selected" /></div>
      <template #footer>
        <Button v-if="selected?.status === 'completed'" variant="danger-ghost" size="lg" @click="voidOpen = true">Batalkan Transaksi</Button>
        <Button v-if="['completed', 'partial_refund'].includes(selected?.status)" size="lg" @click="refundOpen = true">Refund</Button>
        <WhatsAppShareButton v-if="selected && selected.status !== 'void'" :sale-id="selected.id" />
        <Button v-if="selected" size="lg" @click="printSale(selected.id, { isReprint: true })">Cetak Ulang</Button>
        <Button size="lg" @click="detailOpen = false">Tutup</Button>
      </template>
    </Modal>
    <VoidSaleModal v-model:open="voidOpen" :sale="selected" @voided="onVoided" />
    <RefundModal v-model:open="refundOpen" :sale="selected" @refunded="onRefunded" />
  </CashierLayout>
</template>
