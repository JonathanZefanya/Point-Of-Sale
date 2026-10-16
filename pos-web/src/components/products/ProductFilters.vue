<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'

// v-model: { search, category_id, is_active } — nilai kosong = tanpa filter
const filters = defineModel({ type: Object, required: true })

const props = defineProps({
  categories: { type: Array, default: () => [] },
  hasFilter: { type: Boolean, default: false },
})

const emit = defineEmits(['reset'])

const categoryOptions = computed(() => [
  { value: '', label: 'Semua kategori' },
  ...props.categories.map((c) => ({ value: String(c.id), label: c.name })),
])
const statusOptions = [
  { value: '', label: 'Semua status' },
  { value: '1', label: 'Dijual' },
  { value: '0', label: 'Tidak dijual' },
]

// Pencarian menunggu user berhenti mengetik agar tidak memanggil API di setiap huruf
const search = ref(filters.value.search)
let timer = null
watch(search, (value) => {
  clearTimeout(timer)
  timer = setTimeout(() => (filters.value = { ...filters.value, search: value }), 300)
})
watch(
  () => filters.value.search,
  (value) => {
    if (value !== search.value) search.value = value
  },
)

function setFilter(key, value) {
  filters.value = { ...filters.value, [key]: value }
}
</script>

<template>
  <div class="flex flex-wrap items-end gap-2">
    <Input v-model="search" type="search" placeholder="Cari nama, SKU, atau barcode" aria-label="Cari produk" class="w-72" />
    <Select
      :model-value="filters.category_id"
      :options="categoryOptions"
      aria-label="Filter kategori"
      class="w-48"
      @update:model-value="setFilter('category_id', $event)"
    />
    <Select
      :model-value="filters.is_active"
      :options="statusOptions"
      aria-label="Filter status"
      class="w-40"
      @update:model-value="setFilter('is_active', $event)"
    />
    <Button v-if="hasFilter" variant="ghost" @click="emit('reset')">Hapus filter</Button>
  </div>
</template>
