<script setup>
import JsBarcode from 'jsbarcode'
import { onMounted, ref, watch } from 'vue'

/** Barcode sebagai SVG (tajam saat dicetak). EAN-13 bila 13 digit valid, selain itu Code128. */
const props = defineProps({
  value: { type: String, required: true },
  height: { type: Number, default: 40 }, // px pada skala SVG
  barWidth: { type: Number, default: 1.6 },
  fontSize: { type: Number, default: 12 },
})

const svg = ref(null)
const failed = ref(false)

function isEan13(code) {
  if (!/^\d{13}$/.test(code)) return false
  const sum = [...code.slice(0, 12)].reduce((s, d, i) => s + Number(d) * (i % 2 ? 3 : 1), 0)
  return (10 - (sum % 10)) % 10 === Number(code[12])
}

function render() {
  if (!svg.value) return
  try {
    JsBarcode(svg.value, props.value, {
      format: isEan13(props.value) ? 'EAN13' : 'CODE128',
      height: props.height,
      width: props.barWidth,
      fontSize: props.fontSize,
      margin: 0,
      displayValue: true,
      font: 'IBM Plex Mono, monospace',
    })
    failed.value = false
  } catch {
    failed.value = true // karakter yang tidak didukung barcode
  }
}

onMounted(render)
watch(() => [props.value, props.height, props.barWidth, props.fontSize], render)
</script>

<template>
  <svg v-show="!failed" ref="svg" class="h-auto max-w-full" role="img" :aria-label="`Barcode ${value}`" />
  <span v-if="failed" class="font-mono text-xs">{{ value }}</span>
</template>
