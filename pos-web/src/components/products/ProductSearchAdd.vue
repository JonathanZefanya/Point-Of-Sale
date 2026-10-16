<script setup>
import { computed, ref, watch } from 'vue'
import { useProductApi } from '@/composables/useProductApi'
import { formatRupiah } from '@/lib/format'

/**
 * Cari produk lalu tambahkan ke daftar (PO, opname, transfer). Produk bervarian tampil per varian.
 * Enter memilih hasil pertama; bisa juga scan barcode.
 * Emit 'add' { product_id, variant_id, name, sku, barcode, unit, sell_price, cost_price }.
 */
const props = defineProps({
  label: { type: String, default: 'Tambah produk' },
  placeholder: { type: String, default: 'Cari nama, SKU, atau scan barcode' },
  stockOnly: { type: Boolean, default: false }, // hanya produk yang dilacak stoknya
})

const emit = defineEmits(['add'])
const api = useProductApi()

const query = ref('')
const results = ref([])
const loading = ref(false)
const active = ref(0)
let timer = null
let seq = 0

watch(query, (q) => {
  clearTimeout(timer)
  if (!q.trim()) {
    results.value = []
    return
  }
  timer = setTimeout(() => search(q.trim()), 250)
})

async function search(q) {
  const current = ++seq
  loading.value = true
  try {
    const { data } = await api.list({ search: q, per_page: 10, is_active: 1 })
    if (current !== seq) return
    results.value = data.filter((p) => !props.stockOnly || (p.track_stock && p.type !== 'service'))
    active.value = 0
  } finally {
    if (current === seq) loading.value = false
  }
}

// Satu opsi per varian aktif; produk tanpa varian = satu opsi
const options = computed(() =>
  results.value.flatMap((p) => {
    const variants = (p.variants ?? []).filter((v) => v.is_active)
    const base = { product_id: p.id, unit: p.unit }
    if (!variants.length) return [{ ...base, variant_id: null, name: p.name, sku: p.sku, barcode: p.barcode, sell_price: p.sell_price, cost_price: p.cost_price ?? 0 }]
    return variants.map((v) => ({
      ...base, variant_id: v.id, name: `${p.name} - ${v.name}`, sku: v.sku ?? p.sku, barcode: v.barcode, sell_price: v.sell_price, cost_price: v.cost_price ?? p.cost_price ?? 0,
    }))
  }),
)

function pick(option) {
  if (!option) return
  emit('add', option)
  query.value = ''
  results.value = []
}

function onKeydown(e) {
  if (e.key === 'ArrowDown') active.value = Math.min(active.value + 1, options.value.length - 1)
  else if (e.key === 'ArrowUp') active.value = Math.max(active.value - 1, 0)
  else if (e.key === 'Enter') pick(options.value[active.value])
  else if (e.key === 'Escape') query.value = ''
  else return
  e.preventDefault()
}
</script>

<template>
  <div class="relative">
    <label class="mb-1 block font-medium" for="product-search-add">{{ label }}</label>
    <input
      id="product-search-add"
      v-model="query"
      type="search"
      autocomplete="off"
      :placeholder="placeholder"
      class="h-10 w-full rounded border border-border-strong bg-surface px-3 focus:border-primary focus:outline-none"
      role="combobox"
      :aria-expanded="options.length > 0"
      aria-controls="product-search-add-list"
      @keydown="onKeydown"
    >
    <ul
      v-if="query.trim() && (options.length || !loading)"
      id="product-search-add-list"
      role="listbox"
      class="absolute z-20 mt-1 max-h-72 w-full overflow-y-auto rounded border border-border bg-surface shadow-lg"
    >
      <li
        v-for="(o, i) in options"
        :key="`${o.product_id}:${o.variant_id}`"
        role="option"
        :aria-selected="i === active"
        :class="['flex cursor-pointer items-center justify-between gap-2 px-3 py-2', i === active ? 'bg-surface-muted' : 'hover:bg-surface-muted']"
        @mousedown.prevent="pick(o)"
        @mouseenter="active = i"
      >
        <span class="min-w-0">
          <span class="block truncate">{{ o.name }}</span>
          <span class="block font-mono text-sm text-muted">{{ o.sku }}</span>
        </span>
        <span v-if="o.cost_price" class="shrink-0 text-sm text-muted tabular-nums">Modal {{ formatRupiah(o.cost_price) }}</span>
      </li>
      <li v-if="!options.length" class="px-3 py-2 text-muted">Produk "{{ query }}" tidak ditemukan.</li>
    </ul>
  </div>
</template>
