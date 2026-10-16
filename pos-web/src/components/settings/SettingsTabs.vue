<script setup>
/** Tab halaman Pengaturan. Tab dengan isian bermasalah diberi tanda agar mudah ditemukan. */
const active = defineModel({ type: String, required: true })

defineProps({
  tabs: { type: Array, required: true }, // [{ value, label, hasError }]
})
</script>

<template>
  <div class="flex flex-wrap gap-1 border-b border-border" role="tablist" aria-label="Kelompok pengaturan">
    <button
      v-for="tab in tabs"
      :id="`settings-tab-${tab.value}`"
      :key="tab.value"
      type="button"
      role="tab"
      :aria-selected="active === tab.value"
      :aria-controls="`settings-panel-${tab.value}`"
      :class="[
        '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2',
        active === tab.value ? 'border-primary font-medium text-primary' : 'border-transparent text-muted hover:text-text',
      ]"
      @click="active = tab.value"
    >
      {{ tab.label }}
      <span v-if="tab.hasError" class="size-2 rounded-full bg-danger" aria-label="ada isian yang perlu diperbaiki" />
    </button>
  </div>
</template>
