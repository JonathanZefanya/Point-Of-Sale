<script setup>
import { computed } from 'vue'
import Button from '@/components/ui/Button.vue'
import Table from '@/components/ui/Table.vue'
import { formatNumber, formatRupiah } from '@/lib/format'
import StockQuantity from './StockQuantity.vue'

const props = defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  showValue: { type: Boolean, default: false }, // nilai persediaan hanya untuk pemilik/manajer
})

const emit = defineEmits(['open', 'adjust'])

// Satu produk bisa punya beberapa baris (per varian) → key gabungan
const keyed = computed(() => props.rows.map((r) => ({ ...r, row_key: `${r.product_id}:${r.variant_id ?? ''}` })))

const columns = computed(() => [
  { key: 'name', label: 'Produk' },
  { key: 'category', label: 'Kategori', width: '150px' },
  { key: 'quantity', label: 'Stok', align: 'right', width: '170px' },
  { key: 'min_stock', label: 'Minimum', align: 'right', width: '100px' },
  ...(props.showValue ? [{ key: 'stock_value', label: 'Nilai Persediaan', align: 'right', width: '150px' }] : []),
  { key: 'actions', label: '', width: '120px' },
])
</script>

<template>
  <Table :columns="columns" :rows="keyed" row-key="row_key" :loading="loading" clickable @row-click="emit('open', $event)">
    <template #cell-name="{ row }">
      <span class="block font-medium">{{ row.name }}</span>
      <span class="block font-mono text-xs text-muted">{{ row.sku }}</span>
    </template>
    <template #cell-category="{ row }">
      <span v-if="row.category">{{ row.category.name }}</span>
      <span v-else class="text-muted">Tanpa kategori</span>
    </template>
    <template #cell-quantity="{ row }">
      <StockQuantity :quantity="row.quantity" :unit="row.unit" :status="row.status" />
    </template>
    <template #cell-min_stock="{ value }">{{ formatNumber(value) }}</template>
    <template #cell-stock_value="{ value }">{{ formatRupiah(value) }}</template>
    <template #cell-actions="{ row }">
      <div class="text-right">
        <!-- .stop: tombol tidak ikut membuka kartu stok -->
        <Button @click.stop="emit('adjust', row)">Sesuaikan</Button>
      </div>
    </template>
    <template #empty>
      <slot name="empty" />
    </template>
  </Table>
</template>
