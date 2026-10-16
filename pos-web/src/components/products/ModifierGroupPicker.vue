<script setup>
import Checkbox from '@/components/ui/Checkbox.vue'
import { formatRupiah } from '@/lib/format'

// Pilih grup modifier yang berlaku untuk produk ini (2.1). Grupnya dikelola di halaman Modifier.
const selected = defineModel({ type: Array, required: true }) // id grup

defineProps({
  groups: { type: Array, default: () => [] },
})

function describe(group) {
  const rule = group.min_select > 0
    ? `Wajib pilih ${group.min_select === group.max_select ? group.min_select : `${group.min_select}–${group.max_select}`}`
    : `Opsional, maks ${group.max_select}`
  const options = group.modifiers.map((m) => (m.price ? `${m.name} (+${formatRupiah(m.price)})` : m.name)).join(', ')
  return `${rule} · ${options}`
}
</script>

<template>
  <div class="grid gap-2">
    <p v-if="!groups.length" class="text-muted">
      Belum ada grup modifier. Buat dulu di menu Produk → Modifier, mis. "Level Gula" atau "Topping".
    </p>
    <div class="grid gap-2 sm:grid-cols-2">
      <Checkbox v-for="group in groups" :key="group.id" v-model="selected" :value="group.id" :label="group.name" :description="describe(group)" />
    </div>
    <RouterLink :to="{ name: 'modifier-groups' }" class="justify-self-start text-sm text-primary hover:underline">Kelola grup modifier</RouterLink>
  </div>
</template>
