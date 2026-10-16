<script setup>
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'

/**
 * Daftar varian (ukuran/rasa/warna) dengan harga & stok sendiri (2.1).
 * Baris baru boleh diberi stok awal; varian lama diubah stoknya lewat penyesuaian.
 */
const rows = defineModel({ type: Array, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  showStock: { type: Boolean, default: true },
})

function add() {
  rows.value = [...rows.value, { id: null, name: '', sku: '', barcode: '', sell_price: null, cost_price: null, is_active: true, initial_stock: '' }]
}

function remove(index) {
  rows.value = rows.value.filter((_, i) => i !== index)
}

const err = (errors, i, field) => errors[`variants.${i}.${field}`] ?? ''
</script>

<template>
  <div class="grid gap-3">
    <p v-if="!rows.length" class="text-muted">
      Tidak ada varian. Tambahkan bila produk punya pilihan ukuran/rasa/warna dengan harga atau stok berbeda, mis. Regular dan Large.
    </p>

    <div v-for="(row, i) in rows" :key="row.id ?? `new-${i}`" class="grid gap-3 rounded border border-border p-3 sm:grid-cols-6">
      <div class="sm:col-span-2"><Input v-model="row.name" label="Nama varian" required placeholder="mis. Large" :error="err(errors, i, 'name')" /></div>
      <div class="sm:col-span-2"><MoneyInput v-model="row.sell_price" label="Harga jual" required :error="err(errors, i, 'sell_price')" /></div>
      <div class="sm:col-span-2"><MoneyInput v-model="row.cost_price" label="Harga modal" :error="err(errors, i, 'cost_price')" /></div>
      <div class="sm:col-span-2"><Input v-model="row.sku" label="SKU" class="font-mono" placeholder="Otomatis" :error="err(errors, i, 'sku')" /></div>
      <div class="sm:col-span-2"><Input v-model="row.barcode" label="Barcode" class="font-mono" :error="err(errors, i, 'barcode')" @keydown.enter.prevent /></div>
      <div v-if="showStock && !row.id" class="sm:col-span-2">
        <Input v-model.number="row.initial_stock" label="Stok awal" type="number" min="0" inputmode="numeric" placeholder="0" :error="err(errors, i, 'initial_stock')" />
      </div>
      <div class="flex items-center justify-between gap-2 sm:col-span-6">
        <label class="inline-flex items-center gap-2">
          <input v-model="row.is_active" type="checkbox" class="size-4 accent-primary">
          Dijual
        </label>
        <Button variant="danger-ghost" @click="remove(i)">Hapus Varian</Button>
      </div>
    </div>

    <Button class="justify-self-start" @click="add">+ Tambah Varian</Button>
  </div>
</template>
