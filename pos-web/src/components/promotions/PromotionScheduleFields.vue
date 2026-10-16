<script setup>
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import { WEEKDAYS } from '@/lib/promotions'

/** Waktu & tempat berlaku promo: periode tanggal, hari, jam (happy hour), dan outlet. */
const form = defineModel('form', { type: Object, required: true })

defineProps({
  outlets: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
})

const dayButton = (active) => [
  'min-h-10 min-w-12 rounded border px-2 text-sm font-medium',
  active ? 'border-primary bg-primary text-white' : 'border-border-strong bg-surface hover:bg-surface-muted',
]

function toggleDay(day) {
  const days = form.value.rules.days
  form.value.rules.days = days.includes(day) ? days.filter((d) => d !== day) : [...days, day].sort()
}
</script>

<template>
  <fieldset class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
    <legend class="mb-2 font-semibold">Waktu berlaku</legend>
    <Input v-model="form.starts_at" type="date" label="Mulai tanggal" hint="Kosongkan = mulai sekarang." :error="errors.starts_at" />
    <Input v-model="form.ends_at" type="date" label="Sampai tanggal" hint="Kosongkan = tanpa batas." :error="errors.ends_at" />
    <div class="flex flex-col gap-1 sm:col-span-2">
      <p class="font-medium">Hari</p>
      <div class="flex flex-wrap gap-2" role="group" aria-label="Hari berlaku">
        <button v-for="d in WEEKDAYS" :key="d.value" type="button" :class="dayButton(form.rules.days.includes(d.value))" :aria-pressed="form.rules.days.includes(d.value)" @click="toggleDay(d.value)">
          {{ d.label }}
        </button>
      </div>
      <p class="text-sm text-muted">Tidak ada yang dipilih = setiap hari.</p>
    </div>
    <Input v-model="form.rules.start_time" type="time" label="Dari jam" hint="Untuk happy hour. Kosongkan = sepanjang hari." :error="errors['rules.start_time']" />
    <Input v-model="form.rules.end_time" type="time" label="Sampai jam" :error="errors['rules.end_time']" />
    <div v-if="outlets.length > 1" class="flex flex-col gap-1 sm:col-span-2">
      <p class="font-medium">Outlet</p>
      <div class="grid gap-2 sm:grid-cols-3">
        <Checkbox v-for="o in outlets" :key="o.id" v-model="form.outlet_ids" :value="o.id" :label="`${o.code} · ${o.name}`" />
      </div>
      <p class="text-sm text-muted">Tidak ada yang dipilih = semua outlet.</p>
    </div>
  </fieldset>
</template>
