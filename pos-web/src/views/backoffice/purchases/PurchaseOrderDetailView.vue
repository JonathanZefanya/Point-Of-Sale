<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ReceiveGoodsModal from '@/components/purchases/ReceiveGoodsModal.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { PO_STATUSES, usePurchaseApi } from '@/composables/usePurchaseApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatDateTime, formatNumber, formatRupiah } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/** Detail PO (F9): status, item dipesan/diterima, aksi Terima Barang / Ubah / Batalkan. */
const props = defineProps({
  id: { type: String, required: true },
})

const api = usePurchaseApi()
const toast = useToastStore()
const router = useRouter()

const po = ref(null)
const loading = ref(true)
const error = ref('')
const receiveOpen = ref(false)
const cancelOpen = ref(false)
const cancelling = ref(false)

const tone = (status) => PO_STATUSES.find((s) => s.value === status)?.tone ?? 'neutral'

async function load() {
  loading.value = true
  error.value = ''
  try {
    po.value = await api.order(props.id)
  } catch (e) {
    error.value = e.userMessage
  } finally {
    loading.value = false
  }
}

async function cancel() {
  cancelling.value = true
  try {
    po.value = await api.cancel(po.value.id)
    toast.success(`PO ${po.value.number} dibatalkan.`)
    cancelOpen.value = false
  } finally {
    cancelling.value = false
  }
}

const cell = 'px-3 py-2'
onMounted(load)
</script>

<template>
  <BackofficeLayout :title="po ? `PO ${po.number}` : 'Detail PO'">
    <template v-if="po" #header-actions>
      <Button v-if="po.editable" variant="danger-ghost" @click="cancelOpen = true">Batalkan PO</Button>
      <Button v-if="po.editable" @click="router.push({ name: 'purchase-order-edit', params: { id: po.id } })">Ubah</Button>
      <Button v-if="po.receivable" variant="primary" @click="receiveOpen = true">Terima Barang</Button>
    </template>

    <div v-if="loading" class="flex flex-col gap-3"><Skeleton class="h-24" /><Skeleton class="h-48" /></div>
    <div v-else-if="error" class="rounded border border-border bg-surface">
      <EmptyState title="PO gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>
    <div v-else class="flex max-w-5xl flex-col gap-4">
      <dl class="grid gap-4 rounded border border-border bg-surface p-4 sm:grid-cols-4">
        <div><dt class="text-muted">Status</dt><dd class="mt-1"><Badge :tone="tone(po.status)">{{ po.status_label }}</Badge></dd></div>
        <div><dt class="text-muted">Supplier</dt><dd class="font-medium">{{ po.supplier.name }}</dd><dd v-if="po.supplier.phone" class="text-muted">{{ po.supplier.phone }}</dd></div>
        <div><dt class="text-muted">Outlet penerima</dt><dd class="font-medium">{{ po.outlet.code }} · {{ po.outlet.name }}</dd></div>
        <div>
          <dt class="text-muted">Dibuat</dt><dd>{{ formatDateTime(po.created_at) }}</dd>
          <dd class="text-muted">oleh {{ po.created_by?.name }}</dd>
        </div>
        <div v-if="po.received_at"><dt class="text-muted">Terakhir diterima</dt><dd>{{ formatDateTime(po.received_at) }}</dd></div>
        <div v-if="po.note" class="sm:col-span-3"><dt class="text-muted">Catatan</dt><dd>{{ po.note }}</dd></div>
      </dl>

      <div class="overflow-x-auto rounded border border-border bg-surface">
        <table class="w-full text-left">
          <thead class="border-b border-border text-muted">
            <tr>
              <th :class="cell" class="font-medium">Produk</th>
              <th :class="cell" class="text-right font-medium">Dipesan</th>
              <th :class="cell" class="text-right font-medium">Diterima</th>
              <th :class="cell" class="text-right font-medium">Harga beli</th>
              <th :class="cell" class="text-right font-medium">Subtotal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-for="item in po.items" :key="item.id">
              <td :class="cell">{{ item.product_name }}</td>
              <td :class="cell" class="num">{{ formatNumber(item.quantity) }}</td>
              <td :class="cell" class="num">
                <span :class="item.remaining > 0 && item.received_quantity > 0 ? 'text-warning' : ''">{{ formatNumber(item.received_quantity) }}</span>
                <span v-if="item.remaining > 0 && po.receivable" class="block text-sm text-muted">sisa {{ formatNumber(item.remaining) }}</span>
              </td>
              <td :class="cell" class="num">{{ formatRupiah(item.cost_price) }}</td>
              <td :class="cell" class="num">{{ formatRupiah(item.total) }}</td>
            </tr>
          </tbody>
          <tfoot class="border-t border-border">
            <tr><td :class="cell" colspan="4" class="text-right font-semibold">Total</td><td :class="cell" class="num text-lg font-semibold">{{ formatRupiah(po.total) }}</td></tr>
          </tfoot>
        </table>
      </div>
      <p v-if="po.receivable" class="text-muted">Stok outlet bertambah dan harga modal produk diperbarui (rata-rata) saat barang diterima.</p>
    </div>

    <ReceiveGoodsModal v-if="po" v-model:open="receiveOpen" :po="po" @received="(updated) => (po = updated)" />
    <ConfirmModal v-if="po" v-model:open="cancelOpen" :title="`Batalkan PO ${po.number}?`" confirm-label="Batalkan PO" busy-label="Membatalkan..." :busy="cancelling" @confirm="cancel">
      <p>PO tidak bisa diubah atau diterima lagi. Stok tidak berubah karena belum ada barang yang diterima.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
