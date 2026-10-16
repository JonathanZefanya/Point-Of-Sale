<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import Modal from '@/components/ui/Modal.vue'
import { formatDateTime, formatNumber, formatRupiah } from '@/lib/format'
import { useOfflineQueueStore } from '@/stores/offlineQueue'

/**
 * Status antrian transaksi offline di header kasir (FE-3.1): menunggu / menyinkronkan / gagal.
 * Transaksi gagal bisa dikirim ulang atau dibuang (dengan konfirmasi — tidak bisa dibatalkan).
 */
const queue = useOfflineQueueStore()
const open = ref(false)
const discarding = ref(null)

onMounted(() => queue.start())
onBeforeUnmount(() => queue.stop())

async function discard() {
  await queue.discard(discarding.value.client_id)
  discarding.value = null
}
</script>

<template>
  <template v-if="queue.items.length">
    <span aria-hidden="true">·</span>
    <button
      v-if="queue.failed.length"
      type="button"
      class="min-h-touch font-medium text-danger underline"
      @click="open = true"
    >
      {{ formatNumber(queue.failed.length) }} transaksi gagal sinkron
    </button>
    <span v-else class="text-warning" role="status">
      {{ queue.syncing ? 'Menyinkronkan...' : `${formatNumber(queue.pending.length)} transaksi menunggu sinkron` }}
    </span>
  </template>

  <Modal v-model:open="open" title="Transaksi offline gagal sinkron" size="md">
    <p class="mb-3 text-muted">Transaksi ini tersimpan di perangkat tetapi ditolak server. Perbaiki penyebabnya lalu kirim ulang, atau minta manajer memutuskan.</p>
    <ul class="divide-y divide-border rounded border border-border">
      <li v-for="item in queue.failed" :key="item.client_id" class="flex flex-col gap-2 px-3 py-2">
        <div class="flex justify-between gap-2">
          <span>{{ formatDateTime(item.completed_at) }} · {{ formatNumber(item.display?.items ?? 0) }} item</span>
          <span class="font-medium tabular-nums">{{ formatRupiah(item.display?.grand_total ?? 0) }}</span>
        </div>
        <p class="text-danger">{{ item.message }}</p>
        <div class="flex gap-2">
          <Button size="lg" :disabled="queue.syncing" @click="queue.retry(item.client_id)">Kirim Ulang</Button>
          <Button size="lg" variant="danger-ghost" @click="discarding = item">Buang</Button>
        </div>
      </li>
      <li v-if="!queue.failed.length" class="px-3 py-4 text-center text-muted">Tidak ada transaksi yang gagal.</li>
    </ul>
  </Modal>

  <ConfirmModal
    :open="!!discarding"
    title="Buang transaksi offline ini?"
    confirm-label="Buang Transaksi"
    busy-label="Membuang..."
    @update:open="(v) => !v && (discarding = null)"
    @confirm="discard"
  >
    <p>Transaksi {{ discarding ? formatRupiah(discarding.display?.grand_total ?? 0) : '' }} tidak akan tercatat di penjualan maupun stok. Pastikan uangnya sudah ditangani manual.</p>
  </ConfirmModal>
</template>
