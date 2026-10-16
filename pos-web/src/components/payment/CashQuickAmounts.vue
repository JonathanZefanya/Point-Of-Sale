<script setup>
import { computed } from 'vue'
import { formatRupiah } from '@/lib/format'
import { quickCashAmounts } from '@/lib/pricing'

// Tombol nominal cepat tunai: Uang pas, pembulatan ke atas, Rp50.000, Rp100.000 (F3.5)
const props = defineProps({
  due: { type: Number, required: true },
  selected: { type: Number, default: null },
})

const emit = defineEmits(['select'])

const amounts = computed(() => quickCashAmounts(props.due))
</script>

<template>
  <div class="grid grid-cols-3 gap-2">
    <button
      v-for="(amount, index) in amounts"
      :key="amount"
      type="button"
      :class="[
        'min-h-14 rounded border px-2 text-lg font-semibold tabular-nums',
        selected === amount ? 'border-primary bg-primary-soft text-primary' : 'border-border-strong bg-surface hover:bg-surface-muted',
      ]"
      @click="emit('select', amount)"
    >
      <span v-if="index === 0" class="block text-xs font-medium text-muted">Uang pas</span>
      {{ formatRupiah(amount) }}
    </button>
  </div>
</template>
