<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Select from '@/components/ui/Select.vue'
import { ADJUSTMENT_REASONS, useStockApi } from '@/composables/useStockApi'
import { formatNumber } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/**
 * Penyesuaian stok (F8): Tambah/Kurangi → jumlah → alasan → simpan.
 * Default "Kurangi" karena penyesuaian paling sering untuk barang rusak/hilang.
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  // { product_id, variant_id?, name, unit, quantity }
  item: { type: Object, default: null },
  outletId: { type: Number, required: true },
})

const emit = defineEmits(['saved'])

const api = useStockApi()
const toast = useToastStore()

const direction = ref('out')
const amount = ref('')
const reason = ref('damaged')
const note = ref('')
const errors = ref({})
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  direction.value = 'out'
  amount.value = ''
  reason.value = 'damaged'
  note.value = ''
  errors.value = {}
})

const change = computed(() => {
  const n = parseInt(amount.value, 10)
  if (!Number.isInteger(n) || n <= 0) return 0
  return direction.value === 'in' ? n : -n
})
const after = computed(() => (props.item ? props.item.quantity + change.value : 0))

function validate() {
  const next = {}
  if (!change.value) next.quantity_change = 'Isi jumlah lebih dari 0.'
  else if (after.value < 0) next.quantity_change = `Stok hanya ${formatNumber(props.item.quantity)}. Tidak bisa dikurangi ${formatNumber(-change.value)}.`
  if (reason.value === 'other' && !note.value.trim()) next.note = 'Tulis keterangan untuk alasan "Lainnya".'
  errors.value = next
  return !Object.keys(next).length
}

async function save() {
  if (!validate()) return
  saving.value = true
  try {
    await api.adjust({
      outlet_id: props.outletId,
      product_id: props.item.product_id,
      variant_id: props.item.variant_id ?? null,
      quantity_change: change.value,
      reason: reason.value,
      note: note.value.trim() || null,
    })
    toast.success(`Stok ${props.item.name} sekarang ${formatNumber(after.value)} ${props.item.unit}.`)
    open.value = false
    emit('saved')
  } catch (error) {
    if (error.fieldErrors) errors.value = error.fieldErrors
  } finally {
    saving.value = false
  }
}

const directionClass = (value) => [
  'flex-1 min-h-touch rounded border px-3 font-medium',
  direction.value === value ? 'border-primary bg-primary-soft text-primary' : 'border-border-strong bg-surface hover:bg-surface-muted',
]
</script>

<template>
  <Modal v-model:open="open" :title="item ? `Sesuaikan stok ${item.name}` : 'Sesuaikan stok'" :dismissible="!saving">
    <form v-if="item" id="stock-adjust-form" class="flex flex-col gap-4" novalidate @submit.prevent="save">
      <div class="flex gap-2" role="radiogroup" aria-label="Jenis penyesuaian">
        <button type="button" role="radio" :aria-checked="direction === 'out'" :class="directionClass('out')" @click="direction = 'out'">
          Kurangi stok
        </button>
        <button type="button" role="radio" :aria-checked="direction === 'in'" :class="directionClass('in')" @click="direction = 'in'">
          Tambah stok
        </button>
      </div>

      <Input
        v-model="amount"
        label="Jumlah"
        type="number"
        min="1"
        inputmode="numeric"
        autofocus
        :hint="`Satuan: ${item.unit}`"
        :error="errors.quantity_change"
        @input="errors.quantity_change = ''"
      />

      <div class="flex items-center justify-between rounded bg-surface-muted px-3 py-2">
        <span class="text-muted">Stok saat ini → setelah disimpan</span>
        <span class="font-medium tabular-nums">
          {{ formatNumber(item.quantity) }} → <span :class="after < 0 ? 'text-danger' : ''">{{ formatNumber(after) }}</span>
        </span>
      </div>

      <Select v-model="reason" label="Alasan" :options="ADJUSTMENT_REASONS" :error="errors.reason" />
      <Input
        v-model="note"
        label="Keterangan"
        :required="reason === 'other'"
        :placeholder="reason === 'other' ? 'Wajib diisi, mis. ditemukan di gudang' : 'Opsional, mis. kemasan sobek'"
        maxlength="200"
        :error="errors.note"
        @input="errors.note = ''"
      />
    </form>

    <template #footer>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="stock-adjust-form" variant="primary" :loading="saving" loading-text="Menyimpan...">
        Simpan Penyesuaian
      </Button>
    </template>
  </Modal>
</template>
