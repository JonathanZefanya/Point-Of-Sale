<script setup>
import ReceiptPrintArea from '@/components/receipt/ReceiptPrintArea.vue'
import Toast from '@/components/ui/Toast.vue'
</script>

<template>
  <RouterView />
  <Toast />
  <ReceiptPrintArea />
</template>
