<script setup>
import { formatRupiah } from '@/lib/format'
import { PAYMENT_LABEL } from '@/lib/paymentMethods'

// Daftar pembayaran (split) + dibayar + sisa/kembalian — sisa tagihan selalu terlihat (F3.5)
defineProps({
  payments: { type: Array, required: true },
  activeIndex: { type: Number, default: 0 },
  paid: { type: Number, required: true },
  remaining: { type: Number, required: true },
  change: { type: Number, required: true },
})

const emit = defineEmits(['select', 'remove'])
</script>

<template>
  <div class="rounded border border-border">
    <ul v-if="payments.length > 1" class="divide-y divide-border border-b border-border">
      <li v-for="(p, index) in payments" :key="p.id" :class="['flex items-center gap-2 px-3 py-1.5', index === activeIndex && 'bg-primary-soft']">
        <button type="button" class="min-h-10 flex-1 text-left" @click="emit('select', index)">
          {{ PAYMENT_LABEL[p.method] }}
        </button>
        <span class="num">{{ formatRupiah(p.amount ?? 0) }}</span>
        <button
          type="button"
          class="flex size-10 items-center justify-center rounded text-muted hover:text-danger"
          :aria-label="`Hapus pembayaran ${PAYMENT_LABEL[p.method]}`"
          title="Hapus pembayaran ini"
          @click="emit('remove', index)"
        >
          ×
        </button>
      </li>
    </ul>
    <dl class="grid grid-cols-2 gap-y-1 px-3 py-2">
      <dt class="text-muted">Dibayar</dt><dd class="num">{{ formatRupiah(paid) }}</dd>
      <template v-if="remaining > 0">
        <dt class="font-semibold text-danger">Sisa tagihan</dt><dd class="num text-xl font-bold text-danger">{{ formatRupiah(remaining) }}</dd>
      </template>
      <template v-else>
        <dt class="font-semibold">Kembalian</dt><dd class="num text-xl font-bold">{{ formatRupiah(change) }}</dd>
      </template>
    </dl>
  </div>
</template>
