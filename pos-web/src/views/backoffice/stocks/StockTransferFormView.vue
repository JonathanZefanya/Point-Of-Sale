<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ProductSearchAdd from '@/components/products/ProductSearchAdd.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import { useStockApi } from '@/composables/useStockApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatNumber } from '@/lib/format'
import { useOutletStore } from '@/stores/outlet'
import { useToastStore } from '@/stores/toast'

/** Buat transfer (F10): dari outlet terpilih → pilih tujuan → produk & jumlah → Kirim. Stok asal langsung berkurang. */
const api = useStockApi()
const outletStore = useOutletStore()
const toast = useToastStore()
const router = useRouter()

const destinations = ref([])
const toOutletId = ref(null)
const note = ref('')
const items = ref([]) // { product_id, variant_id, name, quantity }
const errors = ref({})
const shortages = ref({}) // "product:variant" → sisa stok (respons 409)
const error = ref('')
const saving = ref(false)

const from = computed(() => outletStore.current)
const options = computed(() => destinations.value.filter((o) => o.id !== from.value?.id).map((o) => ({ value: o.id, label: `${o.code} · ${o.name}` })))
const totalUnits = computed(() => items.value.reduce((s, i) => s + (Number(i.quantity) || 0), 0))
const keyOf = (i) => `${i.product_id}:${i.variant_id ?? ''}`

function add(option) {
  const existing = items.value.find((i) => i.product_id === option.product_id && (i.variant_id ?? null) === option.variant_id)
  if (existing) existing.quantity = (Number(existing.quantity) || 0) + 1
  else items.value.push({ product_id: option.product_id, variant_id: option.variant_id, name: option.name, quantity: 1 })
}

async function submit() {
  saving.value = true
  errors.value = {}
  shortages.value = {}
  error.value = ''
  try {
    const transfer = await api.createTransfer({
      from_outlet_id: from.value?.id,
      to_outlet_id: toOutletId.value,
      note: note.value || null,
      items: items.value.map((i) => ({ product_id: i.product_id, variant_id: i.variant_id, quantity: Number(i.quantity) || 0 })),
    })
    toast.success(`Transfer ${transfer.number} dikirim. Stok ${from.value.code} sudah berkurang.`)
    router.push({ name: 'stock-transfer', params: { id: transfer.id } })
  } catch (e) {
    if (e.status === 409 && e.response?.data?.items) {
      shortages.value = Object.fromEntries(e.response.data.items.map((s) => [`${s.product_id}:${s.variant_id ?? ''}`, s.available]))
      error.value = `${e.userMessage} Kurangi jumlah produk yang ditandai merah.`
    } else {
      errors.value = e.fieldErrors ?? {}
      error.value = e.fieldErrors ? (e.fieldErrors.items ?? '') : e.userMessage
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => api.allOutlets().then((list) => (destinations.value = list)).catch(() => {}))
</script>

<template>
  <BackofficeLayout>
    <form class="flex max-w-4xl flex-col gap-4" novalidate @submit.prevent="submit">
      <section class="grid gap-4 rounded border border-border bg-surface p-4 sm:grid-cols-3">
        <div>
          <p class="font-medium">Dari outlet</p>
          <p class="pt-2">{{ from ? `${from.code} · ${from.name}` : '–' }}</p>
          <p class="text-sm text-muted">Ganti lewat pilihan outlet di atas.</p>
        </div>
        <Select v-model="toOutletId" label="Ke outlet" placeholder="Pilih outlet tujuan" :options="options" required :error="errors.to_outlet_id || errors.from_outlet_id" />
        <Input v-model="note" label="Catatan" maxlength="255" placeholder="mis. stok untuk promo akhir pekan" :error="errors.note" />
      </section>

      <section class="flex flex-col gap-3 rounded border border-border bg-surface p-4">
        <ProductSearchAdd label="Tambah produk" stock-only @add="add" />
        <ul v-if="items.length" class="divide-y divide-border">
          <li v-for="(item, index) in items" :key="keyOf(item)" :class="['flex items-center gap-3 py-2', shortages[keyOf(item)] !== undefined && 'bg-danger-soft']">
            <div class="min-w-0 flex-1">
              <p class="font-medium">{{ item.name }}</p>
              <p v-if="shortages[keyOf(item)] !== undefined" class="text-sm font-medium text-danger" role="alert">Stok tersisa {{ formatNumber(shortages[keyOf(item)]) }}.</p>
              <p v-if="errors[`items.${index}.product_id`] || errors[`items.${index}.quantity`]" class="text-sm text-danger">{{ errors[`items.${index}.product_id`] || errors[`items.${index}.quantity`] }}</p>
            </div>
            <input
              v-model.number="item.quantity"
              type="number"
              min="1"
              inputmode="numeric"
              :aria-label="`Jumlah ${item.name}`"
              class="h-9 w-24 rounded border border-border-strong bg-surface px-2 text-right tabular-nums focus:border-primary focus:outline-none"
            >
            <button type="button" class="flex size-8 items-center justify-center rounded text-muted hover:text-danger" :title="`Hapus ${item.name}`" :aria-label="`Hapus ${item.name}`" @click="items.splice(index, 1)">
              <svg class="size-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="M5 5l10 10M15 5L5 15" stroke-linecap="round" /></svg>
            </button>
          </li>
        </ul>
        <p v-else class="py-6 text-center text-muted">Belum ada produk. Cari produk di atas lalu tekan Enter untuk menambahkannya.</p>
      </section>

      <p v-if="error" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ error }}</p>
      <div class="flex items-center justify-end gap-2">
        <span class="mr-auto text-muted tabular-nums">Total {{ formatNumber(totalUnits) }} unit</span>
        <Button :disabled="saving" @click="router.back()">Batal</Button>
        <Button type="submit" variant="primary" :disabled="!items.length || !toOutletId" :loading="saving" loading-text="Mengirim...">Kirim Transfer</Button>
      </div>
    </form>
  </BackofficeLayout>
</template>
