<script setup>
import { computed, ref, useId } from 'vue'
import { formatNumber, parseRupiah } from '@/lib/format'
import FieldWrapper from './FieldWrapper.vue'

/** v-model berupa integer rupiah (atau null jika kosong). Ditampilkan "15.500" dengan awalan Rp. */
const model = defineModel({ type: Number, default: null })

const props = defineProps({
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  warning: { type: String, default: '' }, // mis. "Harga jual lebih kecil dari harga modal"
  size: { type: String, default: 'md' }, // md | lg
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

defineOptions({ inheritAttrs: false })

const autoId = useId()
const inputId = computed(() => props.id || autoId)

const display = computed(() => (model.value === null || model.value === undefined ? '' : formatNumber(model.value)))

function onInput(event) {
  const value = parseRupiah(event.target.value)
  model.value = value === null ? null : Math.max(0, value)
  // Format ulang langsung agar titik ribuan muncul saat mengetik
  event.target.value = display.value
}

const el = ref(null)
defineExpose({ focus: () => el.value?.focus(), select: () => el.value?.select() })
</script>

<template>
  <FieldWrapper :id="inputId" :label="label" :error="error" :hint="hint" :warning="warning" :required="required">
    <div
      :class="[
        'flex items-center rounded border bg-surface focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20',
        size === 'lg' ? 'min-h-touch text-base' : 'h-9',
        error ? 'border-danger' : warning ? 'border-warning' : 'border-border-strong',
      ]"
    >
      <span class="pl-3 text-muted" aria-hidden="true">Rp</span>
      <input
        :id="inputId"
        ref="el"
        v-bind="$attrs"
        :value="display"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        :required="required"
        :aria-invalid="!!error || undefined"
        :aria-describedby="error ? `${inputId}-error` : hint || warning ? `${inputId}-hint` : undefined"
        class="num h-full w-full min-w-0 bg-transparent px-3 text-text focus:outline-none"
        @input="onInput"
      >
    </div>
  </FieldWrapper>
</template>
