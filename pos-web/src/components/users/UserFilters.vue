<script setup>
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import { ROLE_LABEL } from '@/lib/roles'

// v-model: { search, role, is_active } — nilai kosong = tanpa filter
const filters = defineModel({ type: Object, required: true })

const roleOptions = [{ value: '', label: 'Semua peran' }, ...Object.entries(ROLE_LABEL).map(([value, label]) => ({ value, label }))]
const statusOptions = [
  { value: '', label: 'Semua status' },
  { value: '1', label: 'Aktif' },
  { value: '0', label: 'Nonaktif' },
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

function reset() {
  search.value = ''
  filters.value = { search: '', role: '', is_active: '' }
}
</script>

<template>
  <div class="flex flex-wrap items-end gap-2">
    <Input v-model="search" type="search" placeholder="Cari nama atau email" aria-label="Cari karyawan" class="w-64" />
    <Select
      :model-value="filters.role"
      :options="roleOptions"
      aria-label="Filter peran"
      class="w-44"
      @update:model-value="setFilter('role', $event)"
    />
    <Select
      :model-value="filters.is_active"
      :options="statusOptions"
      aria-label="Filter status"
      class="w-36"
      @update:model-value="setFilter('is_active', $event)"
    />
    <Button v-if="filters.search || filters.role || filters.is_active" variant="ghost" @click="reset">Hapus filter</Button>
  </div>
</template>
