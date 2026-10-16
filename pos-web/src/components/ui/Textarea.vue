<script setup>
import { computed, useId } from 'vue'
import FieldWrapper from './FieldWrapper.vue'

const model = defineModel({ type: String, default: '' })

const props = defineProps({
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  rows: { type: Number, default: 3 },
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

defineOptions({ inheritAttrs: false })

const autoId = useId()
const inputId = computed(() => props.id || autoId)
</script>

<template>
  <FieldWrapper :id="inputId" :label="label" :error="error" :hint="hint" :required="required">
    <textarea
      :id="inputId"
      v-model="model"
      v-bind="$attrs"
      :rows="rows"
      :required="required"
      :aria-invalid="!!error || undefined"
      :aria-describedby="error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined"
      :class="[
        'w-full rounded border bg-surface px-3 py-2 text-text placeholder:text-muted',
        'focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:bg-surface-muted',
        error ? 'border-danger' : 'border-border-strong',
      ]"
    />
  </FieldWrapper>
</template>
