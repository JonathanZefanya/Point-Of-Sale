<script setup>
// v-model boolean, atau array bila `value` diisi (mis. daftar outlet_ids)
const model = defineModel({ type: [Boolean, Array], default: false })

defineProps({
  label: { type: String, required: true },
  description: { type: String, default: '' },
  value: { type: [String, Number], default: undefined },
  disabled: { type: Boolean, default: false },
})
</script>

<template>
  <label
    :class="[
      'flex items-start gap-2.5 rounded border border-border bg-surface px-3 py-2',
      disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-surface-muted',
    ]"
  >
    <input
      v-model="model"
      type="checkbox"
      :value="value"
      :disabled="disabled"
      class="mt-0.5 size-4 shrink-0 accent-primary"
    >
    <span>
      <span class="block text-text">{{ label }}</span>
      <span v-if="description" class="block text-muted">{{ description }}</span>
    </span>
  </label>
</template>
