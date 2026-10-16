<script setup>
import { computed } from 'vue'
import Select from '@/components/ui/Select.vue'
import { useOutletStore } from '@/stores/outlet'

// Pilih outlet di back office. Disembunyikan bila user hanya punya satu outlet.
const outletStore = useOutletStore()

const options = computed(() => outletStore.outlets.map((o) => ({ value: o.id, label: `${o.code} · ${o.name}` })))
const model = computed({
  get: () => outletStore.current?.id ?? null,
  set: (id) => outletStore.select(id),
})
</script>

<template>
  <Select v-if="outletStore.hasMany" v-model="model" :options="options" aria-label="Pilih outlet" class="w-56" />
  <span v-else-if="outletStore.current" class="text-muted">
    Outlet <span class="font-mono font-medium text-text">{{ outletStore.current.code }}</span>
  </span>
</template>
