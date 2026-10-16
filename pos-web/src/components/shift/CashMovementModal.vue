<script setup>
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import { formatRupiah } from '@/lib/format'
import { useShiftStore } from '@/stores/shift'
import { useToastStore } from '@/stores/toast'

/** Kas masuk/keluar di tengah shift (F6.1): jenis → nominal → keterangan. */
const open = defineModel('open', { type: Boolean, default: false })

const shiftStore = useShiftStore()
const toast = useToastStore()

const type = ref('out')
const amount = ref(null)
const note = ref('')
const errors = ref({})
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  type.value = 'out'
  amount.value = null
  note.value = ''
  errors.value = {}
})

async function save() {
  const next = {}
  if (!amount.value) next.amount = 'Isi nominal lebih dari Rp0.'
  if (!note.value.trim()) next.note = 'Tulis keterangan, mis. "Beli es batu" atau "Tambah uang kembalian".'
  errors.value = next
  if (Object.keys(next).length) return

  saving.value = true
  try {
    await shiftStore.addCashMovement(type.value, amount.value, note.value.trim())
    toast.success(`${type.value === 'in' ? 'Kas masuk' : 'Kas keluar'} ${formatRupiah(amount.value)} dicatat.`)
    open.value = false
  } catch (error) {
    if (error.fieldErrors) errors.value = error.fieldErrors
  } finally {
    saving.value = false
  }
}

const typeClass = (value) => [
  'flex-1 min-h-touch rounded border px-3 font-medium',
  type.value === value ? 'border-primary bg-primary-soft text-primary' : 'border-border-strong bg-surface hover:bg-surface-muted',
]
</script>

<template>
  <Modal v-model:open="open" title="Kas masuk / keluar" :dismissible="!saving">
    <form id="cash-movement-form" class="flex flex-col gap-4" novalidate @submit.prevent="save">
      <div class="flex gap-2" role="radiogroup" aria-label="Jenis kas">
        <button type="button" role="radio" :aria-checked="type === 'out'" :class="typeClass('out')" @click="type = 'out'">
          Kas keluar
        </button>
        <button type="button" role="radio" :aria-checked="type === 'in'" :class="typeClass('in')" @click="type = 'in'">
          Kas masuk
        </button>
      </div>
      <MoneyInput v-model="amount" label="Nominal" size="lg" required :error="errors.amount" @input="errors.amount = ''" />
      <Input
        v-model="note"
        label="Keterangan"
        size="lg"
        required
        maxlength="200"
        :placeholder="type === 'out' ? 'mis. Beli es batu' : 'mis. Tambah uang kembalian'"
        :error="errors.note"
        @input="errors.note = ''"
      />
    </form>
    <template #footer>
      <Button size="lg" :disabled="saving" @click="open = false">Batal</Button>
      <Button size="lg" type="submit" form="cash-movement-form" variant="primary" :loading="saving" loading-text="Menyimpan...">
        Simpan {{ type === 'in' ? 'Kas Masuk' : 'Kas Keluar' }}
      </Button>
    </template>
  </Modal>
</template>
