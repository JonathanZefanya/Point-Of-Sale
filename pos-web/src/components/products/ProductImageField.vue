<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'

/** Pilih/ganti/hapus gambar produk dengan pratinjau. Batas sama dengan backend: JPG/PNG/WEBP, 2 MB. */
const file = defineModel('file', { type: [File, null], default: null })
const remove = defineModel('remove', { type: Boolean, default: false })

const props = defineProps({
  currentUrl: { type: String, default: null },
  error: { type: String, default: '' },
})

const MAX_BYTES = 2 * 1024 * 1024
const TYPES = ['image/jpeg', 'image/png', 'image/webp']

const input = ref(null)
const localError = ref('')
const previewUrl = ref(null)

watch(file, (value) => {
  if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  previewUrl.value = value ? URL.createObjectURL(value) : null
})
onBeforeUnmount(() => previewUrl.value && URL.revokeObjectURL(previewUrl.value))

const shownUrl = computed(() => previewUrl.value ?? (remove.value ? null : props.currentUrl))
const message = computed(() => localError.value || props.error)

function onPick(event) {
  const picked = event.target.files?.[0]
  event.target.value = '' // agar file yang sama bisa dipilih ulang
  if (!picked) return

  if (!TYPES.includes(picked.type)) {
    localError.value = 'Gambar harus berformat JPG, PNG, atau WEBP.'
    return
  }
  if (picked.size > MAX_BYTES) {
    localError.value = 'Ukuran gambar maksimal 2 MB. Pilih gambar yang lebih kecil.'
    return
  }
  localError.value = ''
  file.value = picked
  remove.value = false
}

function clear() {
  file.value = null
  remove.value = !!props.currentUrl
  localError.value = ''
}
</script>

<template>
  <div class="flex flex-col gap-1.5">
    <span class="font-medium">Gambar</span>
    <div class="flex items-center gap-4">
      <div class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded border border-border bg-surface-muted">
        <img v-if="shownUrl" :src="shownUrl" alt="Pratinjau gambar produk" class="size-full object-cover">
        <span v-else class="px-2 text-center text-xs text-muted">Belum ada gambar</span>
      </div>
      <div class="flex flex-col items-start gap-2">
        <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" tabindex="-1" @change="onPick">
        <div class="flex gap-2">
          <Button @click="input.click()">{{ shownUrl ? 'Ganti Gambar' : 'Pilih Gambar' }}</Button>
          <Button v-if="shownUrl" variant="danger-ghost" @click="clear">Hapus Gambar</Button>
        </div>
        <p v-if="message" class="text-danger" role="alert">{{ message }}</p>
        <p v-else class="text-muted">Opsional. JPG, PNG, atau WEBP, maksimal 2 MB.</p>
      </div>
    </div>
  </div>
</template>
