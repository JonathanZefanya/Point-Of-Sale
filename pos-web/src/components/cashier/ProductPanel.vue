<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import CategoryTabs from './CategoryTabs.vue'
import ProductGrid from './ProductGrid.vue'
import ProductSearch from './ProductSearch.vue'

/**
 * Panel kiri layar kasir (§7.4): cari/scan, tab kategori, grid produk.
 * Enter di kolom cari (F3.1): barcode/SKU persis → tambah; satu hasil cari → tambah; selain itu beri tahu.
 */
const props = defineProps({
  catalog: { type: Object, required: true }, // hasil useCatalog()
  quantities: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['add'])

const term = ref('')
const categoryId = ref(null)
const message = ref('')
const search = ref(null)

const visible = computed(() => props.catalog.filter(categoryId.value, term.value))

watch(term, () => (message.value = ''))

async function onSubmit(text) {
  const byCode = await props.catalog.findByCode(text)
  const match = byCode ?? (visible.value.length === 1 ? visible.value[0] : null)

  if (match) {
    add(match)
  } else if (visible.value.length > 1) {
    message.value = `Ada ${visible.value.length} produk cocok. Pilih salah satu di bawah.`
  } else {
    message.value = `Produk "${text}" tidak ditemukan. Periksa barcode atau cari dengan nama lain.`
  }
}

function add(product) {
  emit('add', product)
  term.value = ''
  search.value?.focus()
}

defineExpose({ focusSearch: () => search.value?.focus() })
</script>

<template>
  <section class="flex min-w-0 flex-1 flex-col gap-3 p-4">
    <ProductSearch ref="search" v-model="term" :message="message" @submit="onSubmit" />
    <CategoryTabs v-if="catalog.categories.value.length" v-model="categoryId" :categories="catalog.categories.value" />

    <div class="min-h-0 flex-1 overflow-y-auto">
      <div v-if="catalog.error.value" class="rounded border border-border bg-surface">
        <EmptyState title="Produk gagal dimuat." :description="catalog.error.value">
          <Button size="lg" variant="primary" @click="catalog.load()">Coba Lagi</Button>
        </EmptyState>
      </div>
      <div v-else-if="!catalog.loading.value && !catalog.products.value.length" class="rounded border border-border bg-surface">
        <EmptyState
          title="Belum ada produk yang dijual."
          description="Minta pemilik atau manajer menambahkan produk di back office (menu Produk), lalu muat ulang."
        >
          <Button size="lg" variant="primary" @click="catalog.load()">Muat Ulang</Button>
        </EmptyState>
      </div>
      <div v-else-if="!catalog.loading.value && !visible.length" class="rounded border border-border bg-surface">
        <EmptyState title="Tidak ada produk yang cocok." description="Coba kata kunci lain atau pilih kategori Semua.">
          <Button size="lg" @click="term = ''; categoryId = null">Tampilkan Semua</Button>
        </EmptyState>
      </div>
      <ProductGrid v-else :products="visible" :loading="catalog.loading.value" :quantities="quantities" @add="add" />
    </div>
  </section>
</template>
