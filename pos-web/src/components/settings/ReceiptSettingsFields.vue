<script setup>
import Checkbox from '@/components/ui/Checkbox.vue'
import Select from '@/components/ui/Select.vue'

/** Tab Struk (berlaku semua outlet): kertas & cetak otomatis. Hanya pemilik. */
const receipt = defineModel({ type: Object, required: true })

defineProps({
  disabled: { type: Boolean, default: false },
})

const PAPER = [
  { value: 58, label: '58 mm (printer kecil)' },
  { value: 80, label: '80 mm (printer standar)' },
]
</script>

<template>
  <fieldset :disabled="disabled" class="grid gap-3">
    <h2 class="font-semibold">Struk semua outlet</h2>
    <Select v-model="receipt.paper_width" label="Lebar kertas" :options="PAPER" class="sm:max-w-xs" />
    <Checkbox v-model="receipt.auto_print" label="Cetak struk otomatis setelah transaksi" />
    <Checkbox v-model="receipt.show_cashier" label="Tampilkan nama kasir di struk" />
  </fieldset>
</template>
