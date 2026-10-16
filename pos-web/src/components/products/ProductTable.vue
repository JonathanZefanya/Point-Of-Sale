<script setup>
import { computed } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Table from '@/components/ui/Table.vue'
import { formatRupiah } from '@/lib/format'

const props = defineProps({
  products: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  showCost: { type: Boolean, default: false }, // harga modal hanya untuk pemilik/manajer
})

const emit = defineEmits(['open'])

const columns = computed(() => [
  { key: 'name', label: 'Produk' },
  { key: 'sku', label: 'SKU', mono: true, width: '130px' },
  { key: 'category', label: 'Kategori', width: '150px' },
  ...(props.showCost ? [{ key: 'cost_price', label: 'Harga Modal', align: 'right', width: '130px' }] : []),
  { key: 'sell_price', label: 'Harga Jual', align: 'right', width: '130px' },
  { key: 'is_active', label: 'Status', width: '120px' },
])
</script>

<template>
  <Table :columns="columns" :rows="products" :loading="loading" clickable @row-click="emit('open', $event)">
    <template #cell-name="{ row }">
      <div class="flex items-center gap-3">
        <img
          v-if="row.image_url"
          :src="row.image_url"
          alt=""
          class="size-9 shrink-0 rounded-sm border border-border object-cover"
          loading="lazy"
        >
        <span v-else class="size-9 shrink-0 rounded-sm border border-border bg-surface-muted" aria-hidden="true" />
        <div class="min-w-0">
          <span class="block truncate font-medium">{{ row.name }}</span>
          <span v-if="row.barcode" class="block font-mono text-xs text-muted">{{ row.barcode }}</span>
        </div>
      </div>
    </template>
    <template #cell-category="{ row }">
      <span v-if="row.category">{{ row.category.name }}</span>
      <span v-else class="text-muted">Tanpa kategori</span>
    </template>
    <template #cell-cost_price="{ value }">{{ formatRupiah(value) }}</template>
    <template #cell-sell_price="{ row }">
      <span :class="showCost && row.sell_price < row.cost_price && 'text-warning'">{{ formatRupiah(row.sell_price) }}</span>
    </template>
    <template #cell-is_active="{ row }">
      <Badge :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Dijual' : 'Tidak dijual' }}</Badge>
    </template>
    <template #empty>
      <slot name="empty" />
    </template>
  </Table>
</template>
