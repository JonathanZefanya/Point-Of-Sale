<script setup>
import { computed } from 'vue'
import { formatNumber } from '@/lib/format'

/**
 * Daftar hitung opname: stok sistem, input jumlah fisik, selisih.
 * Kosong = belum dihitung (tidak diubah saat diterapkan).
 */
const counts = defineModel('counts', { type: Object, required: true }) // itemId → number | null

const props = defineProps({
  items: { type: Array, required: true },
  readonly: { type: Boolean, default: false },
  highlightId: { type: Number, default: null },
})

const diffOf = (item) => {
  if (props.readonly) return item.difference
  const counted = counts.value[item.id]
  return counted === null || counted === undefined || counted === '' ? null : Number(counted) - item.system_quantity
}

function setCount(item, raw) {
  const value = raw === '' ? null : Math.max(0, Math.floor(Number(raw)))
  counts.value = { ...counts.value, [item.id]: Number.isFinite(value) ? value : null }
}

const rows = computed(() => props.items.map((item) => ({ item, diff: diffOf(item) })))
const cell = 'px-3 py-2'
</script>

<template>
  <div class="overflow-x-auto rounded border border-border bg-surface">
    <table class="w-full text-left">
      <thead class="sticky top-0 border-b border-border bg-surface text-muted">
        <tr>
          <th :class="cell" class="font-medium">Produk</th>
          <th :class="cell" class="w-40 font-medium">SKU</th>
          <th :class="cell" class="w-28 text-right font-medium">{{ readonly ? 'Stok sistem' : 'Stok saat ini' }}</th>
          <th :class="cell" class="w-36 text-right font-medium">Jumlah fisik</th>
          <th :class="cell" class="w-28 text-right font-medium">Selisih</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-border">
        <tr v-for="{ item, diff } in rows" :id="`opname-item-${item.id}`" :key="item.id" :class="item.id === highlightId && 'bg-primary-soft'">
          <td :class="cell">{{ item.product_name }}</td>
          <td :class="cell" class="font-mono text-sm text-muted">{{ item.sku }}</td>
          <td :class="cell" class="num">{{ formatNumber(item.system_quantity) }}</td>
          <td :class="cell" class="text-right">
            <span v-if="readonly" class="num">{{ item.counted_quantity === null ? '–' : formatNumber(item.counted_quantity) }}</span>
            <input
              v-else
              :value="counts[item.id] ?? ''"
              type="number"
              min="0"
              inputmode="numeric"
              placeholder="–"
              :aria-label="`Jumlah fisik ${item.product_name}`"
              :data-opname-input="item.id"
              class="h-9 w-24 rounded border border-border-strong bg-surface px-2 text-right tabular-nums focus:border-primary focus:outline-none"
              @change="setCount(item, $event.target.value)"
              @focus="$event.target.select()"
            >
          </td>
          <td :class="[cell, 'num font-medium', diff > 0 && 'text-success', diff < 0 && 'text-danger', !diff && 'text-muted']">
            {{ diff === null || diff === undefined ? '' : diff > 0 ? `+${formatNumber(diff)}` : formatNumber(diff) }}
          </td>
        </tr>
        <tr v-if="!rows.length"><td :class="cell" colspan="5" class="py-6 text-center text-muted">Tidak ada produk yang cocok dengan filter ini.</td></tr>
      </tbody>
    </table>
  </div>
</template>
