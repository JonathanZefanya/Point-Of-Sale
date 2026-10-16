<script setup>
import { ref } from 'vue'

/**
 * Kolom cari/scan. Kursor otomatis di sini (§7.1 no. 5); scanner mengetik kode lalu Enter.
 * Enter → emit('submit', teks) — halaman yang memutuskan: cocok barcode, satu hasil, atau tidak ditemukan.
 */
const model = defineModel({ type: String, default: '' })

defineProps({
  message: { type: String, default: '' }, // mis. "Produk dengan barcode 123 tidak ditemukan."
})

const emit = defineEmits(['submit'])
const input = ref(null)

function onEnter() {
  if (model.value.trim()) emit('submit', model.value.trim())
}

defineExpose({ focus: () => input.value?.focus() })
</script>

<template>
  <div>
    <div class="relative">
      <svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-muted" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
        <circle cx="9" cy="9" r="6" />
        <path d="M14 14l4 4" stroke-linecap="round" />
      </svg>
      <input
        ref="input"
        v-model="model"
        type="search"
        autofocus
        autocomplete="off"
        placeholder="Cari nama produk atau scan barcode, lalu Enter"
        aria-label="Cari produk atau scan barcode"
        class="min-h-touch w-full rounded border border-border-strong bg-surface py-2 pl-10 pr-4 text-base focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
        @keydown.enter.prevent="onEnter"
        @keydown.esc="model = ''"
      >
    </div>
    <p v-if="message" class="mt-1.5 text-danger" role="alert">{{ message }}</p>
  </div>
</template>
