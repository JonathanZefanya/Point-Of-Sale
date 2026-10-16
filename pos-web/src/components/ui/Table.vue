<script setup>
import Skeleton from './Skeleton.vue'

/**
 * columns: [{ key, label, align?: 'left'|'right', mono?: boolean, width?: string }]
 * Isi sel kustom lewat slot `cell-<key>` ({ row, value }).
 * Kolom angka/uang: align 'right' → otomatis tabular-nums.
 */
const props = defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  rowKey: { type: String, default: 'id' },
  loading: { type: Boolean, default: false },
  skeletonRows: { type: Number, default: 6 },
  clickable: { type: Boolean, default: false },
})

const emit = defineEmits(['row-click'])

function cellClass(col) {
  return [
    'px-3 py-2.5',
    col.align === 'right' && 'num',
    col.mono && 'font-mono',
  ]
}

function onRowKeydown(event, row) {
  if (props.clickable && event.key === 'Enter') emit('row-click', row)
}
</script>

<template>
  <div class="overflow-x-auto rounded border border-border bg-surface">
    <table class="w-full border-collapse text-left">
      <thead class="border-b border-border bg-surface-muted">
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            scope="col"
            :style="col.width ? { width: col.width } : undefined"
            :class="['px-3 py-2 font-medium text-muted', col.align === 'right' && 'text-right']"
          >
            {{ col.label }}
          </th>
        </tr>
      </thead>

      <tbody v-if="loading" aria-busy="true">
        <tr v-for="n in skeletonRows" :key="n" class="border-b border-border last:border-0">
          <td v-for="col in columns" :key="col.key" class="px-3 py-3">
            <Skeleton :width="col.align === 'right' ? '60%' : '80%'" :class="col.align === 'right' && 'ml-auto'" />
          </td>
        </tr>
      </tbody>

      <tbody v-else-if="rows.length">
        <tr
          v-for="row in rows"
          :key="row[rowKey]"
          :tabindex="clickable ? 0 : undefined"
          :class="['border-b border-border last:border-0', clickable && 'cursor-pointer hover:bg-surface-muted focus:bg-surface-muted']"
          @click="clickable && emit('row-click', row)"
          @keydown="onRowKeydown($event, row)"
        >
          <td v-for="col in columns" :key="col.key" :class="cellClass(col)">
            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">{{ row[col.key] ?? '-' }}</slot>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Wajib diisi pemanggil dengan EmptyState yang mengarahkan ke aksi berikutnya -->
    <slot v-if="!loading && !rows.length" name="empty" />
  </div>
</template>
