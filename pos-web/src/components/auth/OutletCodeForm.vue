<script setup>
import { ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'

// Dipakai sekali saat perangkat kasir pertama kali disiapkan
const props = defineProps({
  initialCode: { type: String, default: '' },
})

const emit = defineEmits(['save'])

const code = ref(props.initialCode)
const error = ref('')

function submit() {
  const value = code.value.trim().toUpperCase()
  if (!value) {
    error.value = 'Kode outlet wajib diisi, contoh: JKT01.'
    return
  }
  emit('save', value)
}
</script>

<template>
  <form class="flex w-full max-w-sm flex-col gap-4" novalidate @submit.prevent="submit">
    <div>
      <h1 class="text-xl font-semibold">Siapkan perangkat kasir</h1>
      <p class="mt-1 text-muted">
        Masukkan kode outlet tempat perangkat ini dipakai. Kode bisa dilihat pemilik di menu Outlet. Cukup diisi sekali.
      </p>
    </div>

    <Input
      v-model="code"
      label="Kode outlet"
      size="lg"
      placeholder="JKT01"
      autocomplete="off"
      autofocus
      class="font-mono uppercase"
      :error="error"
      @input="error = ''"
    />

    <Button type="submit" variant="primary" size="lg" block>Simpan Kode Outlet</Button>
  </form>
</template>
