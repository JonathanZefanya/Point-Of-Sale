<script setup>
import { computed, onMounted } from 'vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import { useOutletStore } from '@/stores/outlet'

/**
 * Stok awal saat tambah produk (F7). Outlet hanya ditanyakan bila user punya lebih dari satu;
 * default = outlet yang sedang dipilih di back office.
 */
const quantity = defineModel('quantity', { type: [Number, String], default: '' })
const outletId = defineModel('outletId', { type: [Number, null], default: null })

defineProps({
  unit: { type: String, default: 'pcs' },
  errors: { type: Object, default: () => ({}) },
})

const outletStore = useOutletStore()
const options = computed(() => outletStore.outlets.map((o) => ({ value: o.id, label: `${o.code} · ${o.name}` })))

onMounted(async () => {
  await outletStore.ensureLoaded().catch(() => {})
  outletId.value ??= outletStore.current?.id ?? null
})
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <Input
      v-model.number="quantity"
      label="Stok awal"
      type="number"
      min="0"
      inputmode="numeric"
      placeholder="0"
      :hint="`Jumlah yang ada sekarang (${unit}). Tercatat di kartu stok sebagai Stok Awal.`"
      :error="errors.initial_stock"
    />
    <Select
      v-if="outletStore.hasMany"
      v-model="outletId"
      label="Disimpan di outlet"
      :options="options"
      :error="errors.outlet_id"
    />
  </div>
</template>
