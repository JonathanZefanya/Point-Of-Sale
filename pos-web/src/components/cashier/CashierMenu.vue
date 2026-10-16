<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import Button from '@/components/ui/Button.vue'

/** Tombol [Menu] di header kasir (§7.4). Item: { label, description?, onSelect, tone? } */
defineProps({
  items: { type: Array, required: true },
})

const open = ref(false)
const root = ref(null)

function select(item) {
  open.value = false
  item.onSelect()
}

function onDocumentClick(event) {
  if (open.value && !root.value?.contains(event.target)) open.value = false
}

function onKeydown(event) {
  if (event.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div ref="root" class="relative">
    <Button size="lg" :aria-expanded="open" aria-haspopup="menu" @click="open = !open">Menu</Button>
    <div
      v-if="open"
      role="menu"
      class="absolute right-0 top-full z-30 mt-1 w-72 overflow-hidden rounded-lg border border-border bg-surface shadow-float"
    >
      <button
        v-for="item in items"
        :key="item.label"
        type="button"
        role="menuitem"
        :class="[
          'block min-h-touch w-full border-b border-border px-4 py-2.5 text-left last:border-0 hover:bg-surface-muted',
          item.tone === 'danger' ? 'text-danger' : 'text-text',
        ]"
        @click="select(item)"
      >
        <span class="block font-medium">{{ item.label }}</span>
        <span v-if="item.description" class="block text-sm text-muted">{{ item.description }}</span>
      </button>
    </div>
  </div>
</template>
