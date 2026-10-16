<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import LabelSheet from '@/components/labels/LabelSheet.vue'
import ProductSearchAdd from '@/components/products/ProductSearchAdd.vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Select from '@/components/ui/Select.vue'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatNumber, formatRupiah } from '@/lib/format'

/** Cetak label barcode (FE-2.13): pilih produk & jumlah label → pilih ukuran → Cetak. Tanpa barcode → pakai SKU. */
const LABEL_SIZES = [
  { value: 't50', label: 'Thermal 50 × 30 mm', width: 50, height: 30, padding: 1.5, font: 7, barHeight: 34, barWidth: 1.4 },
  { value: 't38', label: 'Thermal 38 × 25 mm', width: 38, height: 25, padding: 1, font: 6, barHeight: 28, barWidth: 1.1 },
  { value: 'a4', label: 'Kertas A4 (3 × 8 label)', width: 64, height: 33.8, padding: 2, font: 7, barHeight: 34, barWidth: 1.4, sheet: true, columns: 3 },
]

const items = ref([]) // { key, name, price, code, copies }
const sizeValue = ref('t50')
const showName = ref(true)
const showPrice = ref(true)
const size = computed(() => LABEL_SIZES.find((s) => s.value === sizeValue.value))

const labels = computed(() => items.value.flatMap((i) => Array.from({ length: Math.max(0, Math.min(500, Number(i.copies) || 0)) }, (_, n) => ({ key: `${i.key}-${n}`, name: i.name, price: i.price, code: i.code }))))
const preview = computed(() => labels.value.slice(0, size.value.sheet ? 24 : 6))

function add(option) {
  const key = `${option.product_id}:${option.variant_id ?? ''}`
  const existing = items.value.find((i) => i.key === key)
  if (existing) existing.copies = (Number(existing.copies) || 0) + 1
  else items.value.push({ key, name: option.name, price: option.sell_price ?? 0, code: option.barcode || option.sku, usesSku: !option.barcode, copies: 1 })
}

/** Ukuran halaman cetak mengikuti ukuran label; dihapus lagi setelah mencetak. */
function print() {
  const s = size.value
  const style = document.createElement('style')
  style.id = 'label-page-size'
  style.textContent = s.sheet
    ? '@media print { @page { size: A4; margin: 10mm 8mm; } .print-area.label-print { width: auto; padding: 0; } }'
    : `@media print { @page { size: ${s.width}mm ${s.height}mm; margin: 0; } .print-area.label-print { width: ${s.width}mm; padding: 0; } }`
  document.getElementById('label-page-size')?.remove()
  document.head.appendChild(style)
  window.print()
}

const cleanup = () => document.getElementById('label-page-size')?.remove()
window.addEventListener('afterprint', cleanup)
onBeforeUnmount(() => { cleanup(); window.removeEventListener('afterprint', cleanup) })
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" :disabled="!labels.length" @click="print">Cetak {{ formatNumber(labels.length) }} Label</Button>
    </template>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <section class="flex flex-col gap-3 rounded border border-border bg-surface p-4">
        <ProductSearchAdd label="Tambah produk" @add="add" />
        <ul v-if="items.length" class="divide-y divide-border">
          <li v-for="(item, index) in items" :key="item.key" class="flex items-center gap-3 py-2">
            <div class="min-w-0 flex-1">
              <p class="font-medium">{{ item.name }}</p>
              <p class="text-sm text-muted">
                <span class="font-mono">{{ item.code }}</span> · {{ formatRupiah(item.price) }}
                <span v-if="item.usesSku" class="text-warning"> · tanpa barcode, memakai SKU</span>
              </p>
            </div>
            <label class="flex items-center gap-2 text-sm text-muted">
              Jumlah label
              <input
                v-model.number="item.copies"
                type="number"
                min="1"
                max="500"
                inputmode="numeric"
                :aria-label="`Jumlah label ${item.name}`"
                class="h-9 w-20 rounded border border-border-strong bg-surface px-2 text-right tabular-nums text-text focus:border-primary focus:outline-none"
              >
            </label>
            <button type="button" class="flex size-8 items-center justify-center rounded text-muted hover:text-danger" :title="`Hapus ${item.name}`" :aria-label="`Hapus ${item.name}`" @click="items.splice(index, 1)">
              <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" /></svg>
            </button>
          </li>
        </ul>
        <p v-else class="py-6 text-center text-muted">Cari atau scan produk di atas, lalu tekan Enter. Jumlah label bisa diubah per produk.</p>
      </section>

      <section class="flex flex-col gap-3 rounded border border-border bg-surface p-4">
        <div class="flex flex-wrap items-end gap-4">
          <Select v-model="sizeValue" label="Ukuran label" :options="LABEL_SIZES" class="w-60" />
          <Checkbox v-model="showName" label="Nama produk" />
          <Checkbox v-model="showPrice" label="Harga" />
        </div>
        <p class="font-medium">Pratinjau<span v-if="labels.length > preview.length" class="font-normal text-muted"> · {{ formatNumber(preview.length) }} dari {{ formatNumber(labels.length) }} label</span></p>
        <div v-if="labels.length" class="overflow-auto rounded bg-surface-muted p-3">
          <LabelSheet :labels="preview" :size="size" :show-name="showName" :show-price="showPrice" :class="!size.sheet && 'flex flex-wrap gap-2'" />
        </div>
        <p v-else class="text-muted">Pratinjau muncul setelah produk ditambahkan.</p>
        <p class="text-sm text-muted">Saat mencetak, pilih printer label dan pastikan skala 100% (bukan "Sesuaikan ke halaman").</p>
      </section>
    </div>

    <!-- Area cetak: tidak terlihat di layar -->
    <div class="print-only print-area label-print">
      <LabelSheet :labels="labels" :size="size" :show-name="showName" :show-price="showPrice" />
    </div>
  </BackofficeLayout>
</template>
