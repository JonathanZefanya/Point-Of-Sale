<script setup>
import { computed, useId } from 'vue'
import FieldWrapper from './FieldWrapper.vue'

const model = defineModel({ type: [String, Number, null], default: null })

const props = defineProps({
  options: { type: Array, default: () => [] }, // [{ value, label }]
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' }, // mis. "Pilih kategori"
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  size: { type: String, default: 'md' },
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

defineOptions({ inheritAttrs: false })

const autoId = useId()
const selectId = computed(() => props.id || autoId)
</script>

<template>
  <FieldWrapper :id="selectId" :label="label" :error="error" :hint="hint" :required="required">
    <select
      :id="selectId"
      v-model="model"
      v-bind="$attrs"
      :required="required"
      :aria-invalid="!!error || undefined"
      :aria-describedby="error ? `${selectId}-error` : hint ? `${selectId}-hint` : undefined"
      :class="[
        'w-full rounded border bg-surface px-3 text-text',
        'focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20',
        size === 'lg' ? 'min-h-touch text-base' : 'h-9',
        error ? 'border-danger' : 'border-border-strong',
      ]"
    >
      <option v-if="placeholder" :value="null" disabled>{{ placeholder }}</option>
      <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>
  </FieldWrapper>
</template>
