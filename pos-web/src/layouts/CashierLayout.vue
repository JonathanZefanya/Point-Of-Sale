<script setup>
import SyncStatus from '@/components/cashier/SyncStatus.vue'
import { useOnlineStatus } from '@/composables/useOnlineStatus'
import { formatTime } from '@/lib/format'

/**
 * Layar penuh tanpa sidebar (CLAUDE.md §7.4). Header selalu menampilkan
 * outlet, kasir, shift, status koneksi, dan antrian transaksi offline (FE-3.1).
 */
defineProps({
  outletCode: { type: String, default: '' },
  cashierName: { type: String, default: '' },
  shiftOpenedAt: { type: [String, Date], default: null },
})

const { online } = useOnlineStatus()
</script>

<template>
  <div class="cashier-root flex h-screen flex-col overflow-hidden bg-bg">
    <header class="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border bg-surface px-4">
      <div class="flex min-w-0 items-center gap-2 text-muted">
        <span v-if="outletCode" class="font-mono font-medium text-text">{{ outletCode }}</span>
        <template v-if="cashierName">
          <span aria-hidden="true">·</span>
          <span>Kasir: <span class="font-medium text-text">{{ cashierName }}</span></span>
        </template>
        <template v-if="shiftOpenedAt">
          <span aria-hidden="true">·</span>
          <span>Shift {{ formatTime(shiftOpenedAt) }}</span>
        </template>
        <span aria-hidden="true">·</span>
        <span class="inline-flex items-center gap-1.5" :class="online ? 'text-success' : 'text-danger'">
          <span class="size-2 rounded-full bg-current" aria-hidden="true" />
          {{ online ? 'Online' : 'Offline' }}
        </span>
        <SyncStatus />
      </div>

      <div class="flex items-center gap-2">
        <slot name="header-actions" />
      </div>
    </header>

    <main class="flex min-h-0 flex-1">
      <slot />
    </main>
  </div>
</template>
