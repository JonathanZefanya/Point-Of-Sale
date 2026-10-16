<script setup>
import { nextTick, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'

/** Simpan transaksi (F4.1): label opsional, mis. "Meja 3" atau "Bapak baju biru". */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  initialLabel: { type: String, default: '' },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['save'])
const label = ref('')
const input = ref(null)

watch(open, async (isOpen) => {
  if (!isOpen) return
  label.value = props.initialLabel
  await nextTick()
  input.value?.focus()
})
</script>

<template>
  <Modal v-model:open="open" title="Simpan transaksi" size="sm" :dismissible="!saving">
    <form id="hold-form" class="cashier-root flex flex-col gap-3" @submit.prevent="emit('save', label.trim())">
      <Input
        ref="input"
        v-model="label"
        label="Label (opsional)"
        size="lg"
        maxlength="60"
        placeholder="mis. Meja 3 atau Bapak baju biru"
        hint="Keranjang dikosongkan. Buka lagi lewat tombol Tersimpan di atas."
      />
    </form>
    <template #footer>
      <Button size="lg" :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="hold-form" variant="primary" size="lg" :loading="saving" loading-text="Menyimpan...">Simpan Transaksi</Button>
    </template>
  </Modal>
</template>
