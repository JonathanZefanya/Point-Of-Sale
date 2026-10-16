<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import ReceiptContent from '@/components/receipt/ReceiptContent.vue'
import Button from '@/components/ui/Button.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import http from '@/lib/http'

/** Struk online dari link WhatsApp (3.6). Tanpa login; link bertanda tangan dan berlaku 30 hari. */
const route = useRoute()
const receipt = ref(null)
const error = ref('')
const print = () => window.print()

onMounted(async () => {
  try {
    const { data } = await http.get(`/public/receipts/${route.params.id}`, {
      params: { expires: route.query.expires, signature: route.query.signature },
      silent: true,
      skipAuthRedirect: true,
    })
    receipt.value = data.data
  } catch (e) {
    error.value = e.status === 403
      ? 'Link struk tidak valid atau sudah kedaluwarsa. Minta kasir mengirim ulang struk.'
      : e.userMessage
  }
})
</script>

<template>
  <main class="min-h-screen bg-bg px-4 py-6">
    <div class="mx-auto flex max-w-sm flex-col gap-3">
      <Skeleton v-if="!receipt && !error" class="h-96" />
      <p v-else-if="error" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ error }}</p>
      <template v-else>
        <div class="print-area rounded border border-border bg-surface p-4">
          <ReceiptContent :receipt="receipt" />
        </div>
        <Button variant="primary" @click="print">Cetak / Simpan PDF</Button>
      </template>
    </div>
  </main>
</template>
