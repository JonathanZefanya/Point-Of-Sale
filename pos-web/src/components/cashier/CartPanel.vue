<script setup>
import Button from '@/components/ui/Button.vue'
import { ORDER_TYPES } from '@/composables/useTableApi'
import { formatNumber } from '@/lib/format'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'
import CartLine from './CartLine.vue'
import CartTotals from './CartTotals.vue'

/** Panel kanan layar kasir (§7.4): keranjang, total, [Simpan F4] [Bayar F9]. */
defineProps({
  holding: { type: Boolean, default: false },
  fnb: { type: Boolean, default: false }, // outlet punya meja → tampilkan tipe pesanan & meja (3.3)
})

const emit = defineEmits(['hold', 'pay', 'discard-held', 'pick-customer', 'discount', 'tables'])

const cart = useCartStore()
const toast = useToastStore()

/** Hapus langsung + toast Batalkan, bukan konfirmasi (§7.1 no. 8). */
function remove(item) {
  const undo = cart.remove(item.key)
  toast.show({ message: `${item.name} dihapus dari keranjang`, action: { label: 'Batalkan', onClick: undo } })
}
</script>

<template>
  <aside class="flex w-[400px] shrink-0 flex-col border-l border-border bg-surface">
    <div class="flex min-h-14 items-center justify-between gap-2 border-b border-border px-4 py-2">
      <p v-if="cart.heldSaleId" class="min-w-0 truncate">
        Melanjutkan <span class="font-medium">{{ cart.holdLabel || 'transaksi tersimpan' }}</span>
      </p>
      <p v-else class="text-muted">Keranjang <span v-if="cart.itemCount" class="tabular-nums">· {{ formatNumber(cart.itemCount) }} item</span></p>
      <Button v-if="cart.heldSaleId" variant="danger-ghost" @click="emit('discard-held')">Buang</Button>
    </div>

    <!-- Tipe pesanan & meja (F&B 3.3) -->
    <div v-if="fnb" class="flex flex-col gap-2 border-b border-border px-4 py-2">
      <div class="grid grid-cols-3 gap-1" role="group" aria-label="Tipe pesanan">
        <button
          v-for="t in ORDER_TYPES"
          :key="t.value"
          type="button"
          :aria-pressed="cart.orderType === t.value"
          :class="['min-h-touch rounded border px-2 text-sm font-medium', cart.orderType === t.value ? 'border-primary bg-primary text-white' : 'border-border-strong bg-surface hover:bg-surface-muted']"
          @click="cart.setOrderType(t.value); t.value !== 'dine_in' && !cart.heldSaleId && cart.setTable(null)"
        >
          {{ t.label }}
        </button>
      </div>
      <div v-if="cart.orderType === 'dine_in'" class="flex items-center justify-between gap-2">
        <p>Meja: <span class="font-medium">{{ cart.table?.name ?? 'Belum dipilih' }}</span></p>
        <Button size="lg" variant="ghost" @click="emit('tables')">{{ cart.table ? 'Ganti' : 'Pilih Meja' }}</Button>
      </div>
    </div>

    <!-- Pelanggan: Umum [Pilih] (§7.4) -->
    <div class="flex min-h-14 items-center justify-between gap-2 border-b border-border px-4 py-2">
      <p class="min-w-0 truncate">
        Pelanggan: <span class="font-medium">{{ cart.customer?.name ?? 'Umum' }}</span>
        <span v-if="cart.customer?.phone" class="text-sm text-muted"> · {{ cart.customer.phone }}</span>
      </p>
      <Button size="lg" variant="ghost" @click="emit('pick-customer')">{{ cart.customer ? 'Ganti' : 'Pilih' }}</Button>
    </div>

    <ul v-if="!cart.isEmpty" class="min-h-0 flex-1 divide-y divide-border overflow-y-auto">
      <CartLine
        v-for="(item, index) in cart.items"
        :key="item.key"
        :item="item"
        :discount="cart.lineDiscounts[index] ?? 0"
        :available="cart.stockIssues[`${item.product_id}:${item.variant_id ?? ''}`] ?? null"
        @increment="cart.increment(item.key)"
        @decrement="cart.decrement(item.key)"
        @set-quantity="cart.setQuantity(item.key, $event)"
        @set-note="cart.setNote(item.key, $event)"
        @remove="remove(item)"
      />
    </ul>
    <div v-else class="flex flex-1 flex-col items-center justify-center gap-1 px-6 text-center">
      <p class="font-medium">Keranjang kosong</p>
      <p class="text-muted">Scan barcode, ketik nama produk lalu Enter, atau ketuk produk di sebelah kiri.</p>
    </div>

    <!-- Voucher & diskon manual (2.5) -->
    <div v-if="!cart.isEmpty" class="flex min-h-14 items-center justify-between gap-2 border-t border-border px-4 py-2">
      <p class="min-w-0 truncate text-muted">
        <template v-if="cart.voucherCode || cart.manualDiscount">
          <span v-if="cart.voucherCode">Voucher <span class="font-mono text-text">{{ cart.voucherCode }}</span></span>
          <span v-if="cart.voucherCode && cart.manualDiscount"> · </span>
          <span v-if="cart.manualDiscount">Diskon manual</span>
        </template>
        <template v-else>Tanpa voucher/diskon</template>
      </p>
      <Button size="lg" variant="ghost" @click="emit('discount')">Diskon</Button>
    </div>

    <CartTotals />

    <div class="grid grid-cols-2 gap-2 px-4 pb-4">
      <Button size="lg" shortcut="F4" :disabled="cart.isEmpty" :loading="holding" loading-text="Menyimpan..." @click="emit('hold')">Simpan</Button>
      <Button size="lg" variant="primary" shortcut="F9" :disabled="cart.isEmpty" @click="emit('pay')">Bayar</Button>
    </div>
  </aside>
</template>
