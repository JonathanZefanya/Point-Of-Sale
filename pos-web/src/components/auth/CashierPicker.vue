<script setup>
import Skeleton from '@/components/ui/Skeleton.vue'

defineProps({
  users: { type: Array, default: () => [] }, // [{ id, name }]
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['select'])
</script>

<template>
  <div class="w-full max-w-3xl">
    <h1 class="mb-4 text-xl font-semibold">Pilih nama Anda</h1>

    <div v-if="loading" class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3" aria-busy="true">
      <div v-for="n in 6" :key="n" class="flex h-20 items-center rounded border border-border bg-surface px-4">
        <Skeleton width="60%" height="1rem" />
      </div>
    </div>

    <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3">
      <button
        v-for="(user, index) in users"
        :key="user.id"
        type="button"
        :autofocus="index === 0"
        class="flex h-20 items-center gap-3 rounded border border-border bg-surface px-4 text-left text-lg font-medium hover:border-primary hover:bg-primary-soft focus-visible:border-primary"
        @click="emit('select', user)"
      >
        <span
          class="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface-muted font-semibold text-muted"
          aria-hidden="true"
        >{{ user.name.charAt(0).toUpperCase() }}</span>
        <span class="truncate">{{ user.name }}</span>
      </button>
    </div>
  </div>
</template>
