<script setup>
import { computed } from 'vue'
import Button from '@/components/ui/Button.vue'
import { formatTime } from '@/lib/format'

/** Satu tiket di layar dapur (3.4): meja/label, umur pesanan, item, tombol status berikutnya. */
const props = defineProps({
  ticket: { type: Object, required: true },
  now: { type: Number, required: true }, // Date.now() — diperbarui berkala oleh layar dapur
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['advance', 'print'])

const NEXT = {
  new: { status: 'preparing', label: 'Mulai Masak' },
  preparing: { status: 'ready', label: 'Tandai Siap' },
  ready: { status: 'served', label: 'Sudah Disajikan' },
}

const minutes = computed(() => Math.max(0, Math.floor((props.now - new Date(props.ticket.created_at).getTime()) / 60000)))
const late = computed(() => props.ticket.status !== 'ready' && minutes.value >= 15)
const next = computed(() => NEXT[props.ticket.status])
</script>

<template>
  <article :class="['flex flex-col gap-2 rounded border bg-surface p-3', late ? 'border-danger' : 'border-border']">
    <header class="flex items-start justify-between gap-2">
      <div>
        <p class="text-lg font-semibold leading-tight">{{ ticket.label }}</p>
        <p class="text-sm text-muted">
          {{ ticket.order_type_label ?? 'Pesanan' }}<span v-if="ticket.is_addition" class="font-medium text-warning"> · Tambahan</span>
        </p>
      </div>
      <p :class="['text-right text-sm tabular-nums', late ? 'font-semibold text-danger' : 'text-muted']">
        {{ formatTime(ticket.created_at) }}<br>{{ minutes }} menit
      </p>
    </header>

    <ul class="divide-y divide-border border-y border-border">
      <li v-for="(item, i) in ticket.items" :key="i" class="py-1.5">
        <p class="text-base"><span class="font-semibold tabular-nums">{{ item.quantity }}×</span> {{ item.name }}</p>
        <p v-if="item.modifiers?.length" class="text-sm text-muted">{{ item.modifiers.join(', ') }}</p>
        <p v-if="item.note" class="text-sm font-medium text-warning">Catatan: {{ item.note }}</p>
      </li>
    </ul>

    <div class="flex gap-2">
      <Button v-if="next" size="lg" :variant="ticket.status === 'ready' ? 'secondary' : 'primary'" class="flex-1" :disabled="busy" @click="emit('advance', next.status)">
        {{ next.label }}
      </Button>
      <Button size="lg" variant="ghost" @click="emit('print')">Cetak</Button>
    </div>
  </article>
</template>
