<script setup>
import Input from '@/components/ui/Input.vue'
import Textarea from '@/components/ui/Textarea.vue'

/**
 * Pengaturan per outlet (Pemilik & Manajer outlet). part="rates" → tarif pajak & service (tab Penjualan);
 * part="receipt" → header & footer struk (tab Struk).
 */
const outlet = defineModel({ type: Object, required: true })

defineProps({
  part: { type: String, required: true }, // rates | receipt
  errors: { type: Object, default: () => ({}) },
  taxEnabled: { type: Boolean, default: true },
  serviceEnabled: { type: Boolean, default: false },
})
</script>

<template>
  <section class="grid gap-4 sm:grid-cols-2">
    <h2 class="font-semibold sm:col-span-2">
      {{ part === 'rates' ? 'Tarif outlet' : 'Teks struk outlet' }} {{ outlet.code }} · {{ outlet.name }}
      <span class="block text-sm font-normal text-muted">Ganti outlet lewat pilihan outlet di atas.</span>
    </h2>
    <template v-if="part === 'rates'">
      <Input
        v-model.number="outlet.tax_rate"
        label="Tarif pajak (%)"
        type="number"
        min="0"
        max="100"
        step="0.01"
        :disabled="!taxEnabled"
        :hint="taxEnabled ? 'mis. 10 untuk PB1 restoran, 11 untuk PPN.' : 'Pajak sedang tidak dikenakan.'"
        :error="errors['outlet.tax_rate']"
      />
      <Input
        v-model.number="outlet.service_charge_rate"
        label="Service charge (%)"
        type="number"
        min="0"
        max="100"
        step="0.01"
        :disabled="!serviceEnabled"
        :hint="serviceEnabled ? 'Dihitung dari subtotal.' : 'Service charge sedang tidak dikenakan.'"
        :error="errors['outlet.service_charge_rate']"
      />
    </template>
    <template v-else>
      <Textarea
        v-model="outlet.receipt_header"
        label="Header struk"
        maxlength="500"
        placeholder="Kosongkan untuk memakai nama bisnis, alamat, dan telepon outlet"
        hint="Satu baris per baris struk."
        :error="errors['outlet.receipt_header']"
      />
      <Textarea
        v-model="outlet.receipt_footer"
        label="Footer struk"
        maxlength="500"
        placeholder="mis. Terima kasih atas kunjungan Anda"
        :error="errors['outlet.receipt_footer']"
      />
    </template>
  </section>
</template>
