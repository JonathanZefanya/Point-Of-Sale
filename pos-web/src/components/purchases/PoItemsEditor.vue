<script setup>
import { computed } from 'vue'
import ProductSearchAdd from '@/components/products/ProductSearchAdd.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import { formatRupiah } from '@/lib/format'

/** Daftar produk di PO: jumlah & harga beli per baris, subtotal, total. Produk sama → jumlah ditambah. */
const items = defineModel({ type: Array, required: true }) // [{ product_id, variant_id, name, quantity, cost_price }]

defineProps({
  errors: { type: Object, default: () => ({}) },
})

const total = computed(() => items.value.reduce((sum, i) => sum + (Number(i.quantity) || 0) * (Number(i.cost_price) || 0), 0))

function add(option) {
  const existing = items.value.find((i) => i.product_id === option.product_id && (i.variant_id ?? null) === option.variant_id)
  if (existing) existing.quantity = (Number(existing.quantity) || 0) + 1
  else items.value.push({ product_id: option.product_id, variant_id: option.variant_id, name: option.name, quantity: 1, cost_price: option.cost_price ?? 0 })
}

function remove(index) {
  items.value.splice(index, 1)
}

const cell = 'px-3 py-2 align-top'
</script>

<template>
  <section class="flex flex-col gap-3 rounded border border-border bg-surface p-4">
    <ProductSearchAdd label="Tambah produk ke PO" @add="add" />
    <p v-if="errors.items" class="text-sm text-danger" role="alert">{{ errors.items }}</p>

    <table v-if="items.length" class="w-full text-left">
      <thead class="border-b border-border text-muted">
        <tr>
          <th :class="cell" class="font-medium">Produk</th>
          <th :class="cell" class="w-32 text-right font-medium">Jumlah</th>
          <th :class="cell" class="w-48 text-right font-medium">Harga beli / unit</th>
          <th :class="cell" class="w-40 text-right font-medium">Subtotal</th>
          <th :class="cell" class="w-12"><span class="sr-only">Hapus</span></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-border">
        <tr v-for="(item, index) in items" :key="`${item.product_id}:${item.variant_id}`">
          <td :class="cell">
            <p class="pt-2 font-medium">{{ item.name }}</p>
            <p v-if="errors[`items.${index}.product_id`] || errors[`items.${index}.variant_id`]" class="text-sm text-danger">
              {{ errors[`items.${index}.product_id`] || errors[`items.${index}.variant_id`] }}
            </p>
          </td>
          <td :class="cell">
            <input
              v-model.number="item.quantity"
              type="number"
              min="1"
              inputmode="numeric"
              :aria-label="`Jumlah ${item.name}`"
              class="h-9 w-full rounded border border-border-strong bg-surface px-2 text-right tabular-nums focus:border-primary focus:outline-none"
            >
            <p v-if="errors[`items.${index}.quantity`]" class="text-sm text-danger">{{ errors[`items.${index}.quantity`] }}</p>
          </td>
          <td :class="cell">
            <MoneyInput v-model="item.cost_price" :aria-label="`Harga beli ${item.name}`" :error="errors[`items.${index}.cost_price`]" />
          </td>
          <td :class="cell" class="num pt-4">{{ formatRupiah((Number(item.quantity) || 0) * (Number(item.cost_price) || 0)) }}</td>
          <td :class="cell">
            <button type="button" class="mt-1 flex size-8 items-center justify-center rounded text-muted hover:text-danger" :title="`Hapus ${item.name}`" :aria-label="`Hapus ${item.name}`" @click="remove(index)">
              <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" /></svg>
            </button>
          </td>
        </tr>
      </tbody>
      <tfoot class="border-t border-border">
        <tr>
          <td :class="cell" colspan="3" class="text-right font-semibold">Total</td>
          <td :class="cell" class="num text-lg font-semibold">{{ formatRupiah(total) }}</td>
          <td />
        </tr>
      </tfoot>
    </table>
    <p v-else class="py-6 text-center text-muted">Belum ada produk. Cari produk di atas lalu tekan Enter untuk menambahkannya.</p>
  </section>
</template>
