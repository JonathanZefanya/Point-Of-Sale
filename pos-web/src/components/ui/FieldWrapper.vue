<script setup>
// Label + pesan error/peringatan/petunjuk yang sama untuk Input, MoneyInput, dan Select.
// Prioritas: error (memblokir simpan) → warning (boleh tetap simpan) → hint.
defineProps({
  id: { type: String, required: true },
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  warning: { type: String, default: '' },
  required: { type: Boolean, default: false },
})
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <label v-if="label" :for="id" class="font-medium text-text">
      {{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span>
    </label>
    <slot />
    <p v-if="error" :id="`${id}-error`" class="text-danger" role="alert">{{ error }}</p>
    <p v-else-if="warning" :id="`${id}-hint`" class="text-warning" role="status">{{ warning }}</p>
    <p v-else-if="hint" :id="`${id}-hint`" class="text-muted">{{ hint }}</p>
  </div>
</template>
