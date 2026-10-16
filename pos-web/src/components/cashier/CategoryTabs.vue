<script setup>
// "Semua | Minuman | Makanan | ..." — null = semua kategori
const model = defineModel({ type: [Number, null], default: null })

defineProps({
  categories: { type: Array, default: () => [] },
})

const tabClass = (active) => [
  'min-h-touch shrink-0 rounded border px-4 font-medium whitespace-nowrap',
  active ? 'border-primary bg-primary text-white' : 'border-border-strong bg-surface text-text hover:bg-surface-muted',
]
</script>

<template>
  <div class="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Kategori produk">
    <button type="button" role="tab" :aria-selected="model === null" :class="tabClass(model === null)" @click="model = null">Semua</button>
    <button
      v-for="category in categories"
      :key="category.id"
      type="button"
      role="tab"
      :aria-selected="model === category.id"
      :class="tabClass(model === category.id)"
      @click="model = category.id"
    >
      {{ category.name }}
    </button>
  </div>
</template>
