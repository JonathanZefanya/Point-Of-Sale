<script setup>
import { usePrint } from '@/composables/usePrint'
import ReceiptContent from './ReceiptContent.vue'

// Dipasang sekali di App.vue. Tidak terlihat di layar; hanya muncul saat window.print().
const { receipt, reprint } = usePrint()
</script>

<template>
  <div v-if="receipt" class="print-only print-area receipt-print">
    <ReceiptContent :receipt="receipt" :reprint="reprint" />
  </div>
</template>
