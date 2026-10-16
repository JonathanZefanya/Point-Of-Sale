<script setup>
// Hanya untuk development: memeriksa CashierLayout dengan susunan §7.4 (data statis).
import Button from '@/components/ui/Button.vue'
import CashierLayout from '@/layouts/CashierLayout.vue'
import { formatNumber, formatRupiah } from '@/lib/format'

const products = [
  { id: 1, name: 'Kopi Susu', price: 15000 },
  { id: 2, name: 'Teh Manis', price: 8000 },
  { id: 3, name: 'Roti Bakar', price: 18000 },
  { id: 4, name: 'Air Mineral', price: 5000 },
]
const cart = [
  { id: 1, name: 'Kopi Susu', qty: 2, total: 30000 },
  { id: 3, name: 'Roti Bakar', qty: 1, total: 18000 },
]
</script>

<template>
  <CashierLayout outlet-code="JKT01" cashier-name="Rina" :shift-opened-at="new Date()">
    <template #header-actions>
      <Button size="lg">Menu</Button>
    </template>

    <section class="flex min-w-0 flex-1 flex-col gap-3 p-4">
      <input
        type="search"
        placeholder="Cari nama produk atau scan barcode"
        class="min-h-touch w-full rounded border border-border-strong bg-surface px-4 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
        autofocus
      >
      <div class="flex gap-2">
        <Button size="lg" variant="primary">Semua</Button>
        <Button size="lg">Minuman</Button>
        <Button size="lg">Makanan</Button>
      </div>
      <div class="grid grid-cols-[repeat(auto-fill,minmax(140px,1fr))] gap-2">
        <button
          v-for="p in products"
          :key="p.id"
          type="button"
          class="flex min-h-20 flex-col items-start justify-between rounded border border-border bg-surface p-3 text-left hover:border-primary"
        >
          <span class="font-medium">{{ p.name }}</span>
          <span class="tabular-nums text-muted">{{ formatNumber(p.price) }}</span>
        </button>
      </div>
    </section>

    <aside class="flex w-96 shrink-0 flex-col border-l border-border bg-surface">
      <div class="flex items-center justify-between border-b border-border px-4 py-2">
        <span>Pelanggan: <span class="font-medium">Umum</span></span>
        <Button size="lg" variant="ghost">Pilih</Button>
      </div>
      <ul class="flex-1 divide-y divide-border overflow-y-auto">
        <li v-for="item in cart" :key="item.id" class="flex items-center gap-3 px-4 py-3">
          <span class="flex-1">{{ item.name }}</span>
          <span class="num w-8">{{ item.qty }}</span>
          <span class="num w-24">{{ formatNumber(item.total) }}</span>
        </li>
      </ul>
      <div class="space-y-1 border-t border-border px-4 py-3">
        <div class="flex justify-between"><span class="text-muted">Subtotal</span><span class="num">{{ formatNumber(48000) }}</span></div>
        <div class="flex justify-between"><span class="text-muted">Pajak 10%</span><span class="num">{{ formatNumber(4800) }}</span></div>
        <div class="flex items-baseline justify-between pt-1">
          <span class="font-semibold">TOTAL</span>
          <span class="num text-[32px] font-bold leading-tight">{{ formatRupiah(52800) }}</span>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-2 px-4 pb-4">
        <Button size="lg" shortcut="F4">Simpan</Button>
        <Button size="lg" variant="primary" shortcut="F9">Bayar</Button>
      </div>
    </aside>
  </CashierLayout>
</template>
