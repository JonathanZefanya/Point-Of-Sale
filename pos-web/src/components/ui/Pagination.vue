<script setup>
import { computed } from 'vue'
import { formatNumber } from '@/lib/format'
import Button from './Button.vue'

// meta dari API: { current_page, per_page, total, last_page }
const props = defineProps({
  meta: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['change'])

const range = computed(() => {
  if (!props.meta || props.meta.total === 0) return ''
  const { current_page: page, per_page: perPage, total } = props.meta
  const from = (page - 1) * perPage + 1
  const to = Math.min(page * perPage, total)
  return `${formatNumber(from)}–${formatNumber(to)} dari ${formatNumber(total)}`
})
</script>

<template>
  <div v-if="meta && meta.total > 0" class="flex items-center justify-between gap-3 py-3">
    <p class="text-muted tabular-nums">{{ range }}</p>
    <div v-if="meta.last_page > 1" class="flex gap-2">
      <Button :disabled="disabled || meta.current_page <= 1" @click="emit('change', meta.current_page - 1)">Sebelumnya</Button>
      <Button :disabled="disabled || meta.current_page >= meta.last_page" @click="emit('change', meta.current_page + 1)">
        Berikutnya
      </Button>
    </div>
  </div>
</template>
