<script setup>
import { computed, ref, useId } from 'vue'
import FieldWrapper from './FieldWrapper.vue'

const model = defineModel({ type: [String, Number], default: '' })

const props = defineProps({
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  type: { type: String, default: 'text' },
  size: { type: String, default: 'md' }, // md | lg
  required: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

defineOptions({ inheritAttrs: false })

const autoId = useId()
const inputId = computed(() => props.id || autoId)
const describedBy = computed(() => {
  if (props.error) return `${inputId.value}-error`
  if (props.hint) return `${inputId.value}-hint`
  return undefined
})

const el = ref(null)
defineExpose({ focus: () => el.value?.focus(), select: () => el.value?.select() })
</script>

<template>
  <FieldWrapper :id="inputId" :label="label" :error="error" :hint="hint" :required="required">
    <input
      :id="inputId"
      ref="el"
      v-model="model"
      v-bind="$attrs"
      :type="type"
      :required="required"
      :aria-invalid="!!error || undefined"
      :aria-describedby="describedBy"
      :class="[
        'w-full rounded border bg-surface px-3 text-text placeholder:text-muted',
        'focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20',
        'disabled:bg-surface-muted disabled:text-muted',
        size === 'lg' ? 'min-h-touch text-base' : 'h-9',
        error ? 'border-danger' : 'border-border-strong',
      ]"
    >
  </FieldWrapper>
</template>
