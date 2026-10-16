<script setup>
import { formatRupiah } from '@/lib/format'
import BarcodeSvg from './BarcodeSvg.vue'

/**
 * Lembar label barcode (FE-2.13). Ukuran dalam mm agar hasil cetak sesuai kertas label.
 * Thermal: satu label per halaman. A4: grid 3 × 8 label (64 × 33,8 mm).
 */
const props = defineProps({
  labels: { type: Array, required: true }, // [{ key, name, price, code }]
  size: { type: Object, required: true }, // LABEL_SIZES[n]
  showName: { type: Boolean, default: true },
  showPrice: { type: Boolean, default: true },
})

const style = (s) => ({ width: `${s.width}mm`, height: `${s.height}mm`, padding: `${s.padding}mm` })
</script>

<template>
  <div :class="size.sheet ? 'label-grid' : ''" :style="size.sheet ? { gridTemplateColumns: `repeat(${size.columns}, ${size.width}mm)` } : null">
    <div
      v-for="label in props.labels"
      :key="label.key"
      class="label flex flex-col items-center justify-center overflow-hidden bg-white text-center text-black"
      :class="!size.sheet && 'label-page'"
      :style="style(size)"
    >
      <p v-if="showName" class="w-full truncate font-medium leading-tight" :style="{ fontSize: `${size.font}pt` }">{{ label.name }}</p>
      <p v-if="showPrice" class="font-semibold leading-tight tabular-nums" :style="{ fontSize: `${size.font + 1}pt` }">{{ formatRupiah(label.price) }}</p>
      <BarcodeSvg :value="label.code" :height="size.barHeight" :bar-width="size.barWidth" :font-size="size.font * 1.4" />
    </div>
  </div>
</template>

<style scoped>
.label-grid {
  display: grid;
  gap: 0;
}

.label {
  border: 1px dashed #d0d0cc; /* garis bantu di layar */
}

@media print {
  .label {
    border: 0;
  }

  .label-page {
    break-after: page;
  }
}
</style>
