<script setup>
import Skeleton from '@/components/ui/Skeleton.vue'
import { formatNumber } from '@/lib/format'

defineProps({
  products: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  quantities: { type: Object, default: () => ({}) }, // product_id → jumlah di keranjang
})

const emit = defineEmits(['add'])
</script>

<template>
  <div v-if="loading" class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-2" aria-busy="true">
    <div v-for="n in 12" :key="n" class="flex h-24 flex-col justify-between rounded border border-border bg-surface p-3">
      <Skeleton width="80%" height="1rem" />
      <Skeleton width="40%" height="1rem" />
    </div>
  </div>

  <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(150px,1fr))] gap-2">
    <button
      v-for="product in products"
      :key="product.id"
      type="button"
      :class="[
        'relative flex min-h-24 flex-col items-start justify-between gap-2 rounded border bg-surface p-3 text-left',
        'hover:border-primary active:bg-primary-soft',
        quantities[product.id] ? 'border-primary' : 'border-border',
      ]"
      :aria-label="`Tambah ${product.name}`"
      @click="emit('add', product)"
    >
      <span class="line-clamp-2 font-medium leading-snug">{{ product.name }}</span>
      <span class="tabular-nums text-muted">{{ formatNumber(product.sell_price) }}</span>
      <span
        v-if="quantities[product.id]"
        class="absolute right-2 top-2 min-w-6 rounded-full bg-primary px-1.5 text-center text-sm font-semibold text-white tabular-nums"
        :aria-label="`${quantities[product.id]} di keranjang`"
      >{{ quantities[product.id] }}</span>
    </button>
  </div>
</template>
