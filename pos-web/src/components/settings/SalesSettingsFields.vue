<script setup>
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Select from '@/components/ui/Select.vue'

/** Tab Penjualan (berlaku semua outlet): pajak, service charge, pembulatan, diskon manual, poin, dapur. Hanya pemilik. */
const settings = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
  disabled: { type: Boolean, default: false },
})

const ROUNDING_TO = [
  { value: 100, label: 'Rp100' },
  { value: 500, label: 'Rp500' },
  { value: 1000, label: 'Rp1.000' },
]
const ROUNDING_MODE = [
  { value: 'nearest', label: 'Terdekat (Rp15.250 → Rp15.300)' },
  { value: 'up', label: 'Selalu ke atas' },
  { value: 'down', label: 'Selalu ke bawah' },
]
</script>

<template>
  <fieldset :disabled="disabled" class="grid gap-6">
    <section class="grid gap-2">
      <h2 class="font-semibold">Pajak &amp; service charge</h2>
      <Checkbox v-model="settings.tax.enabled" label="Kenakan pajak" description="Tarif pajak diatur per outlet di bawah." />
      <Checkbox
        v-model="settings.tax.inclusive"
        :disabled="!settings.tax.enabled"
        label="Harga jual sudah termasuk pajak"
        description="Bila dicentang, total tidak bertambah; pajak hanya dipisahkan di struk dan laporan."
      />
      <Checkbox v-model="settings.service_charge.enabled" label="Kenakan service charge" description="Untuk kafe/restoran. Tarif diatur per outlet." />
    </section>

    <section class="grid gap-3">
      <h2 class="font-semibold">Pembulatan total</h2>
      <Checkbox v-model="settings.rounding.enabled" label="Bulatkan total belanja" description="Memudahkan kembalian tunai." />
      <div v-if="settings.rounding.enabled" class="grid gap-4 sm:grid-cols-2">
        <Select v-model="settings.rounding.to" label="Kelipatan" :options="ROUNDING_TO" :error="errors['rounding.to']" />
        <Select v-model="settings.rounding.mode" label="Arah pembulatan" :options="ROUNDING_MODE" />
      </div>
    </section>

    <section class="grid gap-3 sm:max-w-xs">
      <h2 class="font-semibold">Diskon manual</h2>
      <Input
        v-model.number="settings.discount.manual_limit_percent"
        label="Batas tanpa persetujuan (%)"
        type="number"
        min="0"
        max="100"
        hint="Diskon di atas batas ini butuh PIN pemilik/manajer."
        :error="errors['discount.manual_limit_percent']"
      />
    </section>

    <section class="grid gap-3">
      <h2 class="font-semibold">Poin pelanggan</h2>
      <Checkbox v-model="settings.loyalty.enabled" label="Aktifkan poin loyalty" description="Pelanggan mendapat poin dari belanja dan bisa menukarnya sebagai pembayaran." />
      <div v-if="settings.loyalty.enabled" class="grid gap-4 sm:grid-cols-2">
        <MoneyInput v-model="settings.loyalty.earn_per" label="Belanja per 1 poin" hint="mis. Rp10.000 = 1 poin" :error="errors['loyalty.earn_per']" />
        <MoneyInput v-model="settings.loyalty.point_value" label="Nilai 1 poin saat ditukar" hint="mis. 1 poin = Rp100" :error="errors['loyalty.point_value']" />
      </div>
    </section>

    <section class="grid gap-3">
      <h2 class="font-semibold">Dapur</h2>
      <Checkbox
        v-model="settings.kitchen.enabled"
        label="Aktifkan Kitchen Display"
        description="Pesanan dari kasir (bill disimpan atau dibayar) otomatis tampil di layar Dapur dan bisa dicetak sebagai tiket."
      />
    </section>
  </fieldset>
</template>
