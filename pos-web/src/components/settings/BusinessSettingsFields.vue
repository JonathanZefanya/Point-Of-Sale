<script setup>
import Input from '@/components/ui/Input.vue'

/** Tab Bisnis: profil yang tampil di struk & laporan. Hanya pemilik. */
const business = defineModel({ type: Object, required: true })

defineProps({
  errors: { type: Object, default: () => ({}) },
})
</script>

<template>
  <section class="grid gap-4 sm:grid-cols-2">
    <h2 class="font-semibold sm:col-span-2">Profil bisnis</h2>
    <Input v-model="business.name" label="Nama bisnis" required :error="errors['business.name']" />
    <Input v-model="business.phone" label="Telepon" :error="errors['business.phone']" />
    <div class="sm:col-span-2"><Input v-model="business.address" label="Alamat" :error="errors['business.address']" /></div>
    <Input v-model="business.npwp" label="NPWP" hint="Dicetak di struk bila diisi." :error="errors['business.npwp']" />
  </section>
</template>
