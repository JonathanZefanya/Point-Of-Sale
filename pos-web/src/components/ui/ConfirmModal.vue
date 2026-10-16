<script setup>
import Button from './Button.vue'
import Modal from './Modal.vue'

/**
 * Konfirmasi untuk aksi yang tidak bisa dibatalkan (hapus data master, void, tutup shift).
 * Untuk aksi yang bisa di-undo, pakai toast "Batalkan" — bukan modal ini (CLAUDE.md §7.1 no. 8).
 */
const open = defineModel('open', { type: Boolean, default: false })

defineProps({
  title: { type: String, required: true },
  confirmLabel: { type: String, required: true }, // kata kerja, mis. "Hapus Produk"
  busyLabel: { type: String, default: 'Memproses...' },
  busy: { type: Boolean, default: false },
  tone: { type: String, default: 'danger' }, // danger | primary
})

const emit = defineEmits(['confirm'])
</script>

<template>
  <Modal v-model:open="open" :title="title" size="sm" :dismissible="!busy">
    <div class="flex flex-col gap-2">
      <slot />
    </div>
    <template #footer>
      <Button :disabled="busy" @click="open = false">Batal</Button>
      <Button :variant="tone" :loading="busy" :loading-text="busyLabel" @click="emit('confirm')">{{ confirmLabel }}</Button>
    </template>
  </Modal>
</template>
