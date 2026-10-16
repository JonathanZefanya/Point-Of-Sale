<script setup>
import { nextTick, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import { formatRupiah } from '@/lib/format'

/**
 * Layar sukses (F3.8): kembalian besar di tengah + Transaksi Baru (Enter).
 * Slot `actions` untuk tombol tambahan (mis. Cetak Struk).
 */
const open = defineModel('open', { type: Boolean, default: false })

defineProps({
  sale: { type: Object, default: null },
})

const emit = defineEmits(['new'])
const newButton = ref(null)

// Fokus ke "Transaksi Baru" agar Enter langsung memulai transaksi berikutnya
watch(open, async (isOpen) => {
  if (!isOpen) return
  await nextTick()
  newButton.value?.$el?.focus()
})

function next() {
  open.value = false
  emit('new')
}
</script>

<template>
  <Modal v-model:open="open" title="Transaksi selesai" size="sm" :dismissible="false">
    <div v-if="sale" class="cashier-root flex flex-col items-center gap-1 py-2 text-center">
      <p v-if="sale.offline" class="rounded border border-warning/30 bg-warning-soft px-3 py-1 text-warning">Tersimpan offline · dikirim otomatis saat online</p>
      <p v-else class="font-mono text-muted">{{ sale.number }}</p>
      <template v-if="sale.change_amount > 0">
        <p class="mt-3 text-muted">Kembalian</p>
        <p class="text-[48px] font-bold leading-none tabular-nums">{{ formatRupiah(sale.change_amount) }}</p>
      </template>
      <p v-else class="mt-3 text-2xl font-semibold">Pembayaran pas</p>
      <p class="mt-2 text-muted">Total {{ formatRupiah(sale.grand_total) }}<template v-if="!sale.offline"> · Dibayar {{ formatRupiah(sale.paid_total) }}</template></p>
      <p v-if="sale.customer && sale.points_earned" class="text-muted">{{ sale.customer.name }} mendapat {{ sale.points_earned }} poin.</p>
    </div>
    <template #footer>
      <!-- Aksi tambahan (Kirim WhatsApp, Cetak Struk) berdampingan; aksi utama selebar penuh di bawah -->
      <div class="grid w-full gap-2">
        <div v-if="$slots.actions" class="grid grid-cols-2 gap-2">
          <slot name="actions" :sale="sale" />
        </div>
        <Button ref="newButton" variant="primary" size="lg" shortcut="Enter" class="w-full" @click="next">Transaksi Baru</Button>
      </div>
    </template>
  </Modal>
</template>
