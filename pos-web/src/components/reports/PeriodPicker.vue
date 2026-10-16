<script setup>
import Input from '@/components/ui/Input.vue'
import { toIsoDate } from '@/lib/format'
import { PERIOD_PRESETS, rangeFor } from '@/lib/periods'

// v-model: { preset, from, to }. Memilih preset mengisi tanggal; mengubah tanggal → Kustom.
const model = defineModel({ type: Object, required: true })

function choose(preset) {
  model.value = preset === 'custom' ? { ...model.value, preset } : { preset, ...rangeFor(preset) }
}

function setDate(key, value) {
  if (value) model.value = { ...model.value, preset: 'custom', [key]: value }
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <div class="inline-flex flex-wrap rounded border border-border-strong bg-surface p-0.5" role="radiogroup" aria-label="Periode">
      <button
        v-for="p in PERIOD_PRESETS"
        :key="p.value"
        type="button"
        role="radio"
        :aria-checked="model.preset === p.value"
        :class="['rounded-sm px-3 py-1.5', model.preset === p.value ? 'bg-primary-soft font-medium text-primary' : 'hover:bg-surface-muted']"
        @click="choose(p.value)"
      >
        {{ p.label }}
      </button>
    </div>
    <template v-if="model.preset === 'custom'">
      <Input :model-value="model.from" type="date" :max="toIsoDate()" aria-label="Dari tanggal" class="w-40" @change="setDate('from', $event.target.value)" />
      <span class="text-muted">sampai</span>
      <Input :model-value="model.to" type="date" :max="toIsoDate()" aria-label="Sampai tanggal" class="w-40" @change="setDate('to', $event.target.value)" />
    </template>
  </div>
</template>
