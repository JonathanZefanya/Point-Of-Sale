<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { usePurchaseApi } from '@/composables/usePurchaseApi'
import { formatNumber } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/**
 * Terima barang (F9.2): jumlah diterima per produk, default = sisa pesanan.
 * Isi 0 untuk barang yang belum datang → PO menjadi "Diterima sebagian".
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  po: { type: Object, required: true },
})

const emit = defineEmits(['received'])

const api = usePurchaseApi()
const toast = useToastStore()
const quantities = ref({})
const note = ref('')
const errors = ref({})
const error = ref('')
const saving = ref(false)

const pending = computed(() => props.po.items.filter((i) => i.remaining > 0))
const totalUnits = computed(() => Object.values(quantities.value).reduce((s, q) => s + (Number(q) || 0), 0))

watch(open, (isOpen) => {
  if (!isOpen) return
  quantities.value = Object.fromEntries(pending.value.map((i) => [i.id, i.remaining]))
  note.value = ''
  errors.value = {}
  error.value = ''
})

async function submit() {
  saving.value = true
  errors.value = {}
  error.value = ''
  try {
    const items = Object.entries(quantities.value).map(([id, qty]) => ({ id: Number(id), quantity: Number(qty) || 0 }))
    const po = await api.receive(props.po.id, items, note.value || null)
    toast.success(po.status === 'received' ? `Semua barang PO ${po.number} diterima. Stok bertambah.` : `Penerimaan sebagian PO ${po.number} dicatat. Stok bertambah.`)
    open.value = false
    emit('received', po)
  } catch (e) {
    errors.value = e.fieldErrors ?? {}
    error.value = e.fieldErrors?.items ?? (e.fieldErrors ? '' : e.userMessage)
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" :title="`Terima barang · ${po.number}`" size="md" :dismissible="!saving">
    <form id="receive-form" class="flex flex-col gap-3" novalidate @submit.prevent="submit">
      <p class="text-muted">Periksa jumlah yang benar-benar datang. Isi 0 bila produk belum datang.</p>
      <ul class="divide-y divide-border rounded border border-border">
        <li v-for="item in pending" :key="item.id" class="flex items-center gap-3 px-3 py-2">
          <div class="min-w-0 flex-1">
            <p class="font-medium">{{ item.product_name }}</p>
            <p class="text-sm text-muted tabular-nums">
              Dipesan {{ formatNumber(item.quantity) }}<template v-if="item.received_quantity"> · sudah diterima {{ formatNumber(item.received_quantity) }}</template>
            </p>
            <p v-if="errors[`items.${item.id}`]" class="text-sm text-danger" role="alert">{{ errors[`items.${item.id}`] }}</p>
          </div>
          <input
            v-model.number="quantities[item.id]"
            type="number"
            min="0"
            :max="item.remaining"
            inputmode="numeric"
            :aria-label="`Jumlah diterima ${item.product_name}`"
            class="h-10 w-24 rounded border border-border-strong bg-surface px-2 text-right tabular-nums focus:border-primary focus:outline-none"
            @focus="$event.target.select()"
          >
        </li>
      </ul>
      <Input v-model="note" label="Catatan (opsional)" maxlength="200" placeholder="mis. no. surat jalan SJ-1029" />
      <p v-if="error" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ error }}</p>
    </form>
    <template #footer>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="receive-form" variant="primary" :disabled="totalUnits <= 0" :loading="saving" loading-text="Menyimpan...">
        Terima {{ formatNumber(totalUnits) }} Unit
      </Button>
    </template>
  </Modal>
</template>
