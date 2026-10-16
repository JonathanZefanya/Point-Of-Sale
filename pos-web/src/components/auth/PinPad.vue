<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Keypad PIN 6 digit. Otomatis mengirim saat digit ke-6 diisi.
 * Bisa dipakai dengan sentuh atau keyboard (angka, Backspace, Esc).
 */
const props = defineProps({
  length: { type: Number, default: 6 },
  disabled: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['submit', 'cancel'])

const digits = ref('')

function press(digit) {
  if (props.disabled || props.busy || digits.value.length >= props.length) return
  digits.value += digit
  if (digits.value.length === props.length) emit('submit', digits.value)
}

function backspace() {
  if (props.disabled || props.busy) return
  digits.value = digits.value.slice(0, -1)
}

function clear() {
  digits.value = ''
}

function onKeydown(event) {
  if (event.ctrlKey || event.metaKey || event.altKey) return
  if (/^\d$/.test(event.key)) {
    event.preventDefault()
    press(event.key)
  } else if (event.key === 'Backspace') {
    event.preventDefault()
    backspace()
  } else if (event.key === 'Escape') {
    emit('cancel')
  }
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))

defineExpose({ clear })

const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9']
const keyClass =
  'flex h-16 items-center justify-center rounded border border-border bg-surface text-2xl font-medium tabular-nums ' +
  'hover:bg-surface-muted active:bg-primary-soft disabled:cursor-not-allowed disabled:opacity-50'
</script>

<template>
  <div class="flex w-full max-w-xs flex-col gap-5">
    <div
      class="flex justify-center gap-3"
      role="status"
      :aria-label="`${digits.length} dari ${length} digit PIN terisi`"
    >
      <span
        v-for="n in length"
        :key="n"
        :class="['size-4 rounded-full border-2', n <= digits.length ? 'border-text bg-text' : 'border-border-strong']"
        aria-hidden="true"
      />
    </div>

    <div class="grid grid-cols-3 gap-2">
      <button
        v-for="key in keys"
        :key="key"
        type="button"
        :class="keyClass"
        :disabled="disabled || busy"
        @click="press(key)"
      >
        {{ key }}
      </button>
      <button type="button" :class="[keyClass, 'text-base']" :disabled="disabled || busy || !digits" @click="clear">
        Kosongkan
      </button>
      <button type="button" :class="keyClass" :disabled="disabled || busy" @click="press('0')">0</button>
      <button
        type="button"
        :class="[keyClass, 'text-base']"
        :disabled="disabled || busy || !digits"
        aria-label="Hapus satu digit"
        @click="backspace"
      >
        Hapus
      </button>
    </div>
  </div>
</template>
