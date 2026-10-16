<script setup>
import { computed, ref } from 'vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import { formatRupiah } from '@/lib/format'
import CategoryField from './CategoryField.vue'
import InitialStockField from './InitialStockField.vue'
import ProductAdvancedFields from './ProductAdvancedFields.vue'
import ProductImageField from './ProductImageField.vue'

/**
 * Form produk satu halaman (F7). Field yang jarang diubah ada di "Pengaturan lanjutan"
 * agar tidak mengganggu user yang hanya butuh produk sederhana.
 */
const form = defineModel({ type: Object, required: true })
const errors = defineModel('errors', { type: Object, required: true })

const props = defineProps({
  categories: { type: Array, default: () => [] },
  currentImageUrl: { type: String, default: null },
  isEdit: { type: Boolean, default: false }, // stok awal hanya saat tambah produk
  modifierGroups: { type: Array, default: () => [] },
})

const emit = defineEmits(['category-created'])


const margin = computed(() => {
  const { sell_price: sell, cost_price: cost } = form.value
  if (sell === null || !cost) return ''
  const diff = sell - cost
  const pct = Math.round((diff / sell) * 100)
  return diff >= 0 ? `Untung ${formatRupiah(diff)} per ${form.value.unit || 'pcs'} (${pct}% dari harga jual).` : ''
})

const priceWarning = computed(() => {
  const { sell_price: sell, cost_price: cost } = form.value
  return sell !== null && cost && sell < cost ? 'Harga jual lebih kecil dari harga modal. Produk ini dijual rugi.' : ''
})

const rules = {
  name: (v) => (v?.trim() ? '' : 'Nama produk wajib diisi.'),
  sell_price: (v) => (form.value.variants.length || (v !== null && v !== undefined) ? '' : 'Harga jual wajib diisi.'),
  sku: (v) => (!v || /^[A-Za-z0-9._-]+$/.test(v) ? '' : 'SKU hanya boleh berisi huruf, angka, titik, strip, dan garis bawah.'),
  unit: (v) => (v?.trim() ? '' : 'Satuan wajib diisi, mis. pcs.'),
}

function check(field) {
  errors.value = { ...errors.value, [field]: rules[field](form.value[field]) }
}

function clearError(field) {
  if (errors.value[field]) errors.value = { ...errors.value, [field]: '' }
}

/** Dipanggil halaman sebelum submit. */
function validateAll() {
  const next = Object.fromEntries(Object.keys(rules).map((f) => [f, rules[f](form.value[f])]))
  errors.value = next
  return Object.values(next).every((e) => !e)
}

const nameInput = ref(null)

defineExpose({ validateAll, focusName: () => nameInput.value?.focus() })
</script>

<template>
  <div class="grid gap-6">
    <section class="grid gap-4 sm:grid-cols-2">
      <div class="sm:col-span-2">
        <Input
          ref="nameInput"
          v-model="form.name"
          label="Nama produk"
          required
          autocomplete="off"
          placeholder="mis. Kopi Susu Gula Aren"
          :error="errors.name"
          @blur="check('name')"
          @input="clearError('name')"
        />
      </div>
      <CategoryField v-model="form.category_id" :categories="categories" :error="errors.category_id" @created="emit('category-created', $event)" />
      <div class="hidden sm:block" />
      <p v-if="form.variants.length" class="rounded border border-border bg-surface-muted px-3 py-2 text-muted sm:col-span-2">
        Harga jual, harga modal, dan stok diatur per varian di Pengaturan lanjutan.
      </p>
      <MoneyInput
        v-if="!form.variants.length"
        v-model="form.sell_price"
        label="Harga jual"
        required
        :error="errors.sell_price"
        :warning="priceWarning"
        :hint="margin || 'Harga yang dibayar pelanggan.'"
        @blur="check('sell_price')"
        @input="clearError('sell_price')"
      />
      <MoneyInput
        v-if="!form.variants.length"
        v-model="form.cost_price"
        label="Harga modal"
        :error="errors.cost_price"
        hint="Harga beli per satuan. Dipakai menghitung laba, tidak terlihat oleh kasir."
      />
    </section>

    <section class="grid gap-4 sm:grid-cols-2">
      <Input
        v-model="form.sku"
        label="SKU"
        autocomplete="off"
        class="font-mono"
        placeholder="Otomatis jika dikosongkan"
        hint="Kode internal produk. Kosongkan agar dibuat otomatis."
        :error="errors.sku"
        @blur="check('sku')"
        @input="clearError('sku')"
      />
      <Input
        v-model="form.barcode"
        label="Barcode"
        autocomplete="off"
        class="font-mono"
        placeholder="Scan atau ketik barcode"
        hint="Arahkan scanner ke kolom ini lalu scan kemasan produk."
        :error="errors.barcode"
        @input="clearError('barcode')"
        @keydown.enter.prevent
      />
    </section>

    <!-- Stok awal hanya untuk produk baru yang dilacak stoknya; setelah itu lewat penyesuaian stok -->
    <InitialStockField
      v-if="!props.isEdit && form.type === 'goods' && form.track_stock && !form.variants.length"
      v-model:quantity="form.initial_stock"
      v-model:outlet-id="form.outlet_id"
      :unit="form.unit || 'pcs'"
      :errors="errors"
    />

    <ProductImageField
      v-model:file="form.image"
      v-model:remove="form.remove_image"
      :current-url="props.currentImageUrl"
      :error="errors.image"
    />

    <Checkbox
      v-model="form.is_active"
      label="Dijual"
      description="Produk tampil di layar kasir. Hilangkan centang untuk menyembunyikan tanpa menghapus."
    />

    <ProductAdvancedFields v-model="form" :errors="errors" :modifier-groups="modifierGroups" :is-edit="isEdit" />
  </div>
</template>
