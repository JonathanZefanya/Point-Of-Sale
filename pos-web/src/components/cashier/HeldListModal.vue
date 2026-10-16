<script setup>
import Modal from '@/components/ui/Modal.vue'
import { formatNumber, formatRupiah, formatTime } from '@/lib/format'

/** Daftar transaksi tersimpan di outlet (F4.2) — ketuk untuk melanjutkan. */
const open = defineModel('open', { type: Boolean, default: false })

defineProps({
  sales: { type: Array, default: () => [] },
  cartHasItems: { type: Boolean, default: false },
})

const emit = defineEmits(['resume'])

function count(sale) {
  return sale.items.reduce((sum, i) => sum + i.quantity, 0)
}
</script>

<template>
  <Modal v-model:open="open" title="Transaksi tersimpan" size="md">
    <div class="cashier-root">
      <p v-if="cartHasItems" class="mb-3 rounded border border-warning/30 bg-warning-soft px-3 py-2 text-warning" role="status">
        Keranjang masih berisi. Simpan atau selesaikan dulu sebelum membuka transaksi lain.
      </p>
      <p v-if="!sales.length" class="py-6 text-center text-muted">Belum ada transaksi tersimpan. Tekan Simpan (F4) untuk menyimpan keranjang.</p>
      <ul v-else class="flex flex-col gap-2">
        <li v-for="sale in sales" :key="sale.id">
          <button
            type="button"
            :disabled="cartHasItems"
            class="flex min-h-16 w-full items-center gap-3 rounded border border-border bg-surface px-4 py-2 text-left hover:border-primary disabled:cursor-not-allowed disabled:opacity-50"
            @click="emit('resume', sale)"
          >
            <span class="min-w-0 flex-1">
              <span class="block truncate font-semibold">{{ sale.hold_label || 'Tanpa label' }}</span>
              <span class="block text-sm text-muted">
                {{ formatNumber(count(sale)) }} item · {{ formatTime(sale.created_at) }} · {{ sale.cashier?.name }}
              </span>
            </span>
            <span class="num font-semibold">{{ formatRupiah(sale.grand_total) }}</span>
          </button>
        </li>
      </ul>
    </div>
  </Modal>
</template>
