<script setup>
/**
 * Empty state selalu mengarahkan ke langkah berikutnya, mis.
 * title="Belum ada produk." + tombol [Tambah Produk] [Import dari Excel] di slot default.
 */
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-col items-start gap-3 px-5 py-8">
    <div>
      <p class="font-semibold text-text">{{ title }}</p>
      <p v-if="description" class="mt-1 text-muted">{{ description }}</p>
    </div>
    <div v-if="$slots.default" class="flex flex-wrap gap-2">
      <slot />
    </div>
  </div>
</template>
