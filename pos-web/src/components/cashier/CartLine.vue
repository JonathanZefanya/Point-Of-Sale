<script setup>
import { computed, nextTick, ref } from 'vue'
import { formatNumber } from '@/lib/format'

/** Satu baris keranjang: −/+ atau ketik jumlah, catatan, hapus (×). */
const props = defineProps({
  item: { type: Object, required: true },
  available: { type: Number, default: null }, // sisa stok bila transaksi ditolak karena stok kurang
  discount: { type: Number, default: 0 }, // diskon promo baris ini (dari quote server)
})

const emit = defineEmits(['increment', 'decrement', 'set-quantity', 'remove', 'set-note'])

const editingNote = ref(false)
const noteInput = ref(null)
const short = computed(() => props.available !== null && props.item.quantity > props.available)

async function openNote() {
  editingNote.value = true
  await nextTick()
  noteInput.value?.focus()
}

const iconButton = 'flex size-11 shrink-0 items-center justify-center rounded border border-border-strong bg-surface text-lg hover:bg-surface-muted disabled:opacity-40'
</script>

<template>
  <li :class="['px-4 py-3', short && 'bg-danger-soft']">
    <div class="flex items-start gap-2">
      <div class="min-w-0 flex-1">
        <p class="font-medium leading-snug">{{ item.name }}</p>
        <p v-if="item.modifiers?.length" class="text-sm text-muted">{{ item.modifiers.map((m) => m.name).join(', ') }}</p>
        <p class="text-sm text-muted tabular-nums">@ {{ formatNumber(item.unit_price) }}</p>
        <p v-if="short" class="text-sm font-medium text-danger" role="alert">Stok tersisa {{ formatNumber(available) }}. Kurangi jumlahnya.</p>
      </div>
      <div class="pt-0.5 text-right">
        <p :class="['num font-medium', discount && 'text-sm text-muted line-through']">{{ formatNumber(item.unit_price * item.quantity) }}</p>
        <p v-if="discount" class="num font-medium">{{ formatNumber(item.unit_price * item.quantity - discount) }}</p>
      </div>
      <button type="button" :class="[iconButton, 'size-9 border-transparent text-muted hover:text-danger']" title="Hapus item" aria-label="Hapus item" @click="emit('remove')">
        <svg class="size-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" /></svg>
      </button>
    </div>

    <div class="mt-2 flex items-center gap-2">
      <button type="button" :class="iconButton" :disabled="item.quantity <= 1" aria-label="Kurangi jumlah" @click="emit('decrement')">−</button>
      <input
        :value="item.quantity"
        type="number"
        min="1"
        inputmode="numeric"
        aria-label="Jumlah"
        class="h-11 w-16 rounded border border-border-strong bg-surface text-center tabular-nums focus:border-primary focus:outline-none"
        @change="emit('set-quantity', $event.target.value); $event.target.value = item.quantity"
        @focus="$event.target.select()"
      >
      <button type="button" :class="iconButton" aria-label="Tambah jumlah" @click="emit('increment')">+</button>
      <button v-if="!item.note && !editingNote" type="button" class="ml-auto min-h-touch px-2 text-sm text-muted hover:text-text" @click="openNote">+ Catatan</button>
    </div>

    <input
      v-if="item.note || editingNote"
      ref="noteInput"
      :value="item.note"
      maxlength="200"
      placeholder="Catatan, mis. tanpa gula"
      aria-label="Catatan item"
      class="mt-2 h-10 w-full rounded border border-border bg-surface px-3 text-sm focus:border-primary focus:outline-none"
      @input="emit('set-note', $event.target.value)"
      @blur="editingNote = false"
    >
  </li>
</template>
