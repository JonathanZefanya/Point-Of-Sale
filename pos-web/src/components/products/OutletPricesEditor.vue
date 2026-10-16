<script setup>
import Checkbox from '@/components/ui/Checkbox.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import { formatRupiah } from '@/lib/format'

/**
 * Harga & ketersediaan per outlet (2.8). Harga kosong = harga jual default.
 * Produk bervarian memakai harga varian di semua outlet; yang bisa diatur hanya ketersediaannya.
 */
const prices = defineModel({ type: Object, required: true }) // outlet_id → { sell_price, is_available }

defineProps({
  outlets: { type: Array, required: true },
  defaultPrice: { type: Number, default: null },
  hasVariants: { type: Boolean, default: false },
})

const row = (outletId) => prices.value[outletId] ?? { sell_price: null, is_available: true }

function update(outletId, patch) {
  prices.value = { ...prices.value, [outletId]: { ...row(outletId), ...patch } }
}
</script>

<template>
  <div class="divide-y divide-border rounded border border-border">
    <div v-for="o in outlets" :key="o.id" class="grid items-start gap-3 px-3 py-2 sm:grid-cols-[1fr_auto_14rem]">
      <p class="pt-2"><span class="font-mono font-medium">{{ o.code }}</span> · {{ o.name }}</p>
      <Checkbox
        :model-value="row(o.id).is_available"
        label="Dijual di outlet ini"
        @update:model-value="update(o.id, { is_available: $event })"
      />
      <MoneyInput
        v-if="!hasVariants"
        :model-value="row(o.id).sell_price"
        :aria-label="`Harga khusus ${o.name}`"
        :placeholder="defaultPrice ? `Default ${formatRupiah(defaultPrice)}` : 'Harga default'"
        :disabled="!row(o.id).is_available"
        @update:model-value="update(o.id, { sell_price: $event })"
      />
    </div>
    <p class="px-3 py-2 text-sm text-muted">
      {{ hasVariants ? 'Produk bervarian memakai harga varian di semua outlet.' : 'Kosongkan harga untuk memakai harga jual utama.' }}
    </p>
  </div>
</template>
