<script setup>
defineProps({
  width: { type: String, default: '100%' },
  height: { type: String, default: '0.875rem' },
})
</script>

<template>
  <span
    class="block animate-pulse rounded-sm bg-border"
    :style="{ width, height }"
    aria-hidden="true"
  />
</template>
