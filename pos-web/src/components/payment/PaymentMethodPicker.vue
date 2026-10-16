<script setup>
import { PAYMENT_METHODS } from '@/lib/paymentMethods'

// Tunai · QRIS · Debit · Kredit · E-Wallet · Transfer (F3.5)
const model = defineModel({ type: String, default: 'cash' })

defineProps({
  methods: { type: Array, default: () => PAYMENT_METHODS },
})
</script>

<template>
  <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Metode pembayaran">
    <button
      v-for="method in methods"
      :key="method.value"
      type="button"
      role="radio"
      :aria-checked="model === method.value"
      :class="[
        'min-h-12 rounded border px-3 font-medium',
        model === method.value ? 'border-primary bg-primary text-white' : 'border-border-strong bg-surface hover:bg-surface-muted',
      ]"
      @click="model = method.value"
    >
      {{ method.label }}
    </button>
  </div>
</template>
