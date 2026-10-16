<script setup>
import { computed, ref, watch } from 'vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Select from '@/components/ui/Select.vue'
import { isItemType, isOrderType, isPercentType } from '@/lib/promotions'

/** Bagian form promo yang bergantung pada jenis: target produk, minimum belanja, atau beli X gratis Y. */
const rules = defineModel('rules', { type: Object, required: true })

const props = defineProps({
  type: { type: String, required: true },
  products: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
})

// Cakupan diskon produk: semua / kategori / produk tertentu
const scope = ref('all')
// Dihitung ulang saat jenis berubah atau form baru dibuka (objek rules berganti)
watch(
  () => [props.type, rules.value],
  () => {
    scope.value = rules.value.product_ids?.length ? 'products' : rules.value.category_ids?.length ? 'categories' : 'all'
  },
  { immediate: true },
)
watch(scope, (value) => {
  if (value !== 'products') rules.value.product_ids = []
  if (value !== 'categories') rules.value.category_ids = []
})

const productOptions = computed(() => props.products.map((p) => ({ value: p.id, label: p.name })))
const productSearch = ref('')
const filteredProducts = computed(() => {
  const q = productSearch.value.trim().toLowerCase()
  return q ? props.products.filter((p) => p.name.toLowerCase().includes(q)) : props.products
})

const scopeButton = (active) => [
  'min-h-10 flex-1 rounded border px-3 text-sm font-medium',
  active ? 'border-primary bg-primary text-white' : 'border-border-strong bg-surface hover:bg-surface-muted',
]
</script>

<template>
  <div v-if="isItemType(type)" class="flex flex-col gap-2 sm:col-span-2">
    <p class="font-medium">Berlaku untuk</p>
    <div class="flex gap-2" role="group" aria-label="Cakupan diskon">
      <button type="button" :class="scopeButton(scope === 'all')" :aria-pressed="scope === 'all'" @click="scope = 'all'">Semua produk</button>
      <button type="button" :class="scopeButton(scope === 'categories')" :aria-pressed="scope === 'categories'" @click="scope = 'categories'">Kategori tertentu</button>
      <button type="button" :class="scopeButton(scope === 'products')" :aria-pressed="scope === 'products'" @click="scope = 'products'">Produk tertentu</button>
    </div>
    <div v-if="scope === 'categories'" class="grid gap-2 sm:grid-cols-3">
      <Checkbox v-for="c in categories" :key="c.id" v-model="rules.category_ids" :value="c.id" :label="c.name" />
      <p v-if="!categories.length" class="text-muted">Belum ada kategori.</p>
    </div>
    <template v-if="scope === 'products'">
      <Input v-model="productSearch" type="search" placeholder="Cari produk" aria-label="Cari produk" />
      <div class="grid max-h-56 gap-2 overflow-y-auto sm:grid-cols-2">
        <Checkbox v-for="p in filteredProducts" :key="p.id" v-model="rules.product_ids" :value="p.id" :label="p.name" />
      </div>
    </template>
    <p v-if="errors['rules.product_ids'] || errors['rules.category_ids']" class="text-sm text-danger">{{ errors['rules.product_ids'] || errors['rules.category_ids'] }}</p>
  </div>

  <template v-else-if="isOrderType(type)">
    <MoneyInput v-model="rules.min_purchase" label="Minimum belanja" hint="Kosongkan bila tanpa minimum." :error="errors['rules.min_purchase']" />
    <MoneyInput v-if="isPercentType(type)" v-model="rules.max_discount" label="Maksimal potongan" hint="Kosongkan bila tanpa batas." :error="errors['rules.max_discount']" />
  </template>

  <template v-else-if="type === 'buy_x_get_y'">
    <Select v-model="rules.buy_product_id" label="Produk yang dibeli" placeholder="Pilih produk" :options="productOptions" required :error="errors['rules.buy_product_id']" />
    <Input v-model.number="rules.buy_quantity" type="number" min="1" label="Jumlah beli" required :error="errors['rules.buy_quantity']" />
    <Select v-model="rules.get_product_id" label="Produk gratis" placeholder="Pilih produk" :options="productOptions" required :error="errors['rules.get_product_id']" />
    <Input v-model.number="rules.get_quantity" type="number" min="1" label="Jumlah gratis" required :error="errors['rules.get_quantity']" />
    <p class="text-sm text-muted sm:col-span-2">Bila produk beli dan gratis sama, mis. beli 2 gratis 1: pelanggan mengambil 3, membayar 2.</p>
  </template>
</template>
