<script setup>
import { storeToRefs } from 'pinia'
import { formatNumber, formatRupiah } from '@/lib/format'
import { useCartStore } from '@/stores/cart'

// Angka dari getter store cart — template hanya menampilkan (CLAUDE.md §6)
const { subtotal, serviceCharge, taxTotal, rounding, grandTotal, taxPercentLabel, pricing, promotions, manualDiscountAmount } = storeToRefs(useCartStore())
</script>

<template>
  <dl class="space-y-1 border-t border-border px-4 py-3">
    <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd class="num">{{ formatNumber(subtotal) }}</dd></div>
    <!-- Rincian diskon dari server: per promo lalu diskon manual -->
    <div v-for="promo in promotions" :key="promo.id" class="flex justify-between text-success">
      <dt class="truncate pr-2">{{ promo.name }}</dt><dd class="num">−{{ formatNumber(promo.amount) }}</dd>
    </div>
    <div v-if="manualDiscountAmount" class="flex justify-between text-success">
      <dt>Diskon manual</dt><dd class="num">−{{ formatNumber(manualDiscountAmount) }}</dd>
    </div>
    <div v-if="serviceCharge" class="flex justify-between"><dt class="text-muted">Service charge</dt><dd class="num">{{ formatNumber(serviceCharge) }}</dd></div>
    <div v-if="taxTotal" class="flex justify-between">
      <dt class="text-muted">Pajak {{ taxPercentLabel }}<span v-if="pricing.taxInclusive"> (termasuk)</span></dt>
      <dd class="num">{{ formatNumber(taxTotal) }}</dd>
    </div>
    <div v-if="rounding" class="flex justify-between"><dt class="text-muted">Pembulatan</dt><dd class="num">{{ formatNumber(rounding) }}</dd></div>
    <div class="flex items-baseline justify-between pt-1">
      <dt class="font-semibold">TOTAL</dt>
      <dd class="num text-[32px] font-bold leading-tight">{{ formatRupiah(grandTotal) }}</dd>
    </div>
  </dl>
</template>
