<script setup>
import { formatNumber, formatRupiah } from '@/lib/format'

/** Tiga panel ringkas di bawah grafik: produk terlaris, metode bayar, stok menipis. */
defineProps({
  topProducts: { type: Array, default: () => [] },
  payments: { type: Array, default: () => [] },
  stock: { type: Object, required: true }, // { low, out, items }
})
</script>

<template>
  <div class="grid gap-3 lg:grid-cols-3">
    <section class="rounded border border-border bg-surface">
      <h2 class="border-b border-border px-4 py-2.5 font-semibold">Produk terlaris</h2>
      <ol v-if="topProducts.length" class="divide-y divide-border">
        <li v-for="(p, i) in topProducts" :key="p.product_id" class="flex items-center gap-3 px-4 py-2">
          <span class="w-4 text-muted tabular-nums">{{ i + 1 }}</span>
          <span class="min-w-0 flex-1 truncate">{{ p.name }}</span>
          <span class="num text-muted">{{ formatNumber(p.quantity) }} terjual</span>
        </li>
      </ol>
      <p v-else class="px-4 py-6 text-muted">Belum ada penjualan pada tanggal ini.</p>
    </section>

    <section class="rounded border border-border bg-surface">
      <h2 class="border-b border-border px-4 py-2.5 font-semibold">Metode pembayaran</h2>
      <ul v-if="payments.length" class="divide-y divide-border">
        <li v-for="p in payments" :key="p.method" class="flex items-center justify-between gap-3 px-4 py-2">
          <span>{{ p.label }} <span class="text-sm text-muted">· {{ formatNumber(p.transactions) }} trx</span></span>
          <span class="num">{{ formatRupiah(p.amount) }}</span>
        </li>
      </ul>
      <p v-else class="px-4 py-6 text-muted">Belum ada pembayaran pada tanggal ini.</p>
    </section>

    <section class="rounded border border-border bg-surface">
      <div class="flex items-center justify-between border-b border-border px-4 py-2.5">
        <h2 class="font-semibold">Stok menipis</h2>
        <RouterLink :to="{ name: 'stocks', query: { status: 'low' } }" class="text-sm text-primary hover:underline">
          Lihat semua ({{ formatNumber(stock.low) }})
        </RouterLink>
      </div>
      <ul v-if="stock.items.length" class="divide-y divide-border">
        <li v-for="item in stock.items" :key="item.product_id" class="flex items-center justify-between gap-3 px-4 py-2">
          <span class="min-w-0 truncate">{{ item.name }}</span>
          <span :class="['num', item.status === 'out' ? 'font-medium text-danger' : 'text-warning']">
            {{ formatNumber(item.quantity) }} {{ item.unit }} <span class="text-xs">· min {{ formatNumber(item.min_stock) }}</span>
          </span>
        </li>
      </ul>
      <p v-else class="px-4 py-6 text-muted">Semua stok di atas batas minimum.</p>
    </section>
  </div>
</template>
