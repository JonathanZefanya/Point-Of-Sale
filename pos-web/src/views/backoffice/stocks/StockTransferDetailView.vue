<script setup>
import { onMounted, ref } from 'vue'
import ReceiveTransferModal from '@/components/stocks/ReceiveTransferModal.vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useStockApi } from '@/composables/useStockApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatDateTime, formatNumber } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/** Detail transfer (F10): outlet tujuan menerima, outlet asal bisa membatalkan selama di perjalanan. */
const props = defineProps({
  id: { type: String, required: true },
})

const api = useStockApi()
const toast = useToastStore()
const transfer = ref(null)
const loading = ref(true)
const error = ref('')
const receiveOpen = ref(false)
const cancelOpen = ref(false)
const busy = ref(false)
const tones = { in_transit: 'warning', received: 'success', cancelled: 'neutral' }

async function load() {
  loading.value = true
  error.value = ''
  try {
    transfer.value = await api.transfer(props.id)
  } catch (e) {
    error.value = e.userMessage
  } finally {
    loading.value = false
  }
}

async function cancel() {
  busy.value = true
  try {
    transfer.value = await api.cancelTransfer(transfer.value.id)
    cancelOpen.value = false
    toast.success(`Transfer ${transfer.value.number} dibatalkan. Stok kembali ke ${transfer.value.from_outlet.code}.`)
  } finally {
    busy.value = false
  }
}

const cell = 'px-3 py-2'
onMounted(load)
</script>

<template>
  <BackofficeLayout :title="transfer ? `Transfer ${transfer.number}` : 'Transfer Stok'">
    <template v-if="transfer" #header-actions>
      <Button v-if="transfer.can_cancel" variant="danger-ghost" @click="cancelOpen = true">Batalkan Transfer</Button>
      <Button v-if="transfer.can_receive" variant="primary" @click="receiveOpen = true">Terima Barang</Button>
    </template>

    <div v-if="loading" class="flex flex-col gap-3"><Skeleton class="h-24" /><Skeleton class="h-48" /></div>
    <div v-else-if="error" class="rounded border border-border bg-surface">
      <EmptyState title="Transfer gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>
    <div v-else class="flex max-w-4xl flex-col gap-4">
      <dl class="grid gap-4 rounded border border-border bg-surface p-4 sm:grid-cols-4">
        <div><dt class="text-muted">Status</dt><dd class="mt-1"><Badge :tone="tones[transfer.status]">{{ transfer.status_label }}</Badge></dd></div>
        <div><dt class="text-muted">Dari</dt><dd class="font-medium">{{ transfer.from_outlet.code }} · {{ transfer.from_outlet.name }}</dd></div>
        <div><dt class="text-muted">Ke</dt><dd class="font-medium">{{ transfer.to_outlet.code }} · {{ transfer.to_outlet.name }}</dd></div>
        <div><dt class="text-muted">Dikirim</dt><dd>{{ formatDateTime(transfer.created_at) }}</dd><dd class="text-muted">oleh {{ transfer.created_by?.name }}</dd></div>
        <div v-if="transfer.received_at"><dt class="text-muted">Diterima</dt><dd>{{ formatDateTime(transfer.received_at) }}</dd><dd class="text-muted">oleh {{ transfer.received_by?.name }}</dd></div>
        <div v-if="transfer.note" class="sm:col-span-2"><dt class="text-muted">Catatan pengirim</dt><dd>{{ transfer.note }}</dd></div>
        <div v-if="transfer.receive_note" class="sm:col-span-2"><dt class="text-muted">Catatan penerima</dt><dd>{{ transfer.receive_note }}</dd></div>
      </dl>

      <div class="overflow-x-auto rounded border border-border bg-surface">
        <table class="w-full text-left">
          <thead class="border-b border-border text-muted">
            <tr>
              <th :class="cell" class="font-medium">Produk</th>
              <th :class="cell" class="w-32 text-right font-medium">Dikirim</th>
              <th :class="cell" class="w-32 text-right font-medium">Diterima</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-for="item in transfer.items" :key="item.id">
              <td :class="cell">{{ item.product_name }}</td>
              <td :class="cell" class="num">{{ formatNumber(item.quantity) }}</td>
              <td :class="[cell, 'num', item.received_quantity !== null && item.received_quantity < item.quantity && 'font-medium text-danger']">
                {{ item.received_quantity === null ? '–' : formatNumber(item.received_quantity) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="transfer.status === 'in_transit'" class="text-muted">
        Stok {{ transfer.from_outlet.code }} sudah berkurang. Stok {{ transfer.to_outlet.code }} bertambah setelah barang diterima.
      </p>
    </div>

    <ReceiveTransferModal v-if="transfer" v-model:open="receiveOpen" :transfer="transfer" @received="(t) => (transfer = t)" />
    <ConfirmModal v-if="transfer" v-model:open="cancelOpen" :title="`Batalkan transfer ${transfer.number}?`" confirm-label="Batalkan Transfer" busy-label="Membatalkan..." :busy="busy" @confirm="cancel">
      <p>Stok dikembalikan ke {{ transfer.from_outlet.code }}. Pastikan barang belum dikirim atau sudah kembali.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
