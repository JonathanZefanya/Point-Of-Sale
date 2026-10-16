<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { useStockApi } from '@/composables/useStockApi'
import { formatNumber } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/** Terima transfer (F10): cek jumlah per produk (default = jumlah dikirim). Kurang → catatan wajib. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  transfer: { type: Object, required: true },
})

const emit = defineEmits(['received'])

const api = useStockApi()
const toast = useToastStore()
const quantities = ref({})
const note = ref('')
const errors = ref({})
const error = ref('')
const saving = ref(false)

const short = computed(() => props.transfer.items.some((i) => (Number(quantities.value[i.id]) || 0) < i.quantity))

watch(open, (isOpen) => {
  if (!isOpen) return
  quantities.value = Object.fromEntries(props.transfer.items.map((i) => [i.id, i.quantity]))
  note.value = ''
  errors.value = {}
  error.value = ''
})

async function submit() {
  saving.value = true
  errors.value = {}
  error.value = ''
  try {
    const items = Object.entries(quantities.value).map(([id, q]) => ({ id: Number(id), quantity: Number(q) || 0 }))
    const transfer = await api.receiveTransfer(props.transfer.id, items, note.value || null)
    toast.success(`Transfer ${transfer.number} diterima. Stok ${transfer.to_outlet.code} bertambah.`)
    open.value = false
    emit('received', transfer)
  } catch (e) {
    errors.value = e.fieldErrors ?? {}
    error.value = e.fieldErrors ? '' : e.userMessage
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" :title="`Terima transfer · ${transfer.number}`" size="md" :dismissible="!saving">
    <form id="receive-transfer-form" class="flex flex-col gap-3" novalidate @submit.prevent="submit">
      <p class="text-muted">Hitung barang yang datang. Ubah jumlahnya bila ada yang kurang atau rusak.</p>
      <ul class="divide-y divide-border rounded border border-border">
        <li v-for="item in transfer.items" :key="item.id" class="flex items-center gap-3 px-3 py-2">
          <div class="min-w-0 flex-1">
            <p class="font-medium">{{ item.product_name }}</p>
            <p class="text-sm text-muted tabular-nums">Dikirim {{ formatNumber(item.quantity) }}</p>
            <p v-if="errors[`items.${item.id}`]" class="text-sm text-danger" role="alert">{{ errors[`items.${item.id}`] }}</p>
          </div>
          <input
            v-model.number="quantities[item.id]"
            type="number"
            min="0"
            :max="item.quantity"
            inputmode="numeric"
            :aria-label="`Jumlah diterima ${item.product_name}`"
            class="h-10 w-24 rounded border border-border-strong bg-surface px-2 text-right tabular-nums focus:border-primary focus:outline-none"
            @focus="$event.target.select()"
          >
        </li>
      </ul>
      <Input
        v-model="note"
        :label="short ? 'Catatan (wajib karena ada yang kurang)' : 'Catatan (opsional)'"
        maxlength="255"
        placeholder="mis. 1 botol pecah di perjalanan"
        :required="short"
        :error="errors.note"
      />
      <p v-if="error" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ error }}</p>
    </form>
    <template #footer>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="receive-transfer-form" variant="primary" :loading="saving" loading-text="Menyimpan...">Terima Barang</Button>
    </template>
  </Modal>
</template>
