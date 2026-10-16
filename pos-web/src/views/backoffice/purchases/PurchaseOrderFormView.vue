<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PoItemsEditor from '@/components/purchases/PoItemsEditor.vue'
import SupplierFormModal from '@/components/purchases/SupplierFormModal.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { usePurchaseApi } from '@/composables/usePurchaseApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { useOutletStore } from '@/stores/outlet'
import { useToastStore } from '@/stores/toast'

/** Buat/ubah PO (F9.1): pilih supplier → tambah produk & qty & harga beli → Simpan Draf / Simpan & Kirim. */
const props = defineProps({
  id: { type: String, default: null },
})

const api = usePurchaseApi()
const outletStore = useOutletStore()
const toast = useToastStore()
const router = useRouter()
const route = useRoute()

const form = ref({ supplier_id: Number(route.query.supplier_id) || null, outlet_id: null, note: '', items: [] })
const suppliers = ref([])
const errors = ref({})
const loading = ref(true)
const loadError = ref('')
const saving = ref(null) // 'draft' | 'ordered'
const supplierFormOpen = ref(false)
const number = ref('')

const supplierOptions = computed(() => suppliers.value.map((s) => ({ value: s.id, label: s.name })))
const outletOptions = computed(() => outletStore.outlets.map((o) => ({ value: o.id, label: `${o.code} · ${o.name}` })))

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [list, po] = await Promise.all([api.suppliers({ per_page: 100 }), props.id ? api.order(props.id) : null, outletStore.ensureLoaded()])
    suppliers.value = list.data
    form.value.outlet_id = outletStore.current?.id ?? null
    if (po) {
      if (!po.editable) return router.replace({ name: 'purchase-order', params: { id: po.id } })
      number.value = po.number
      form.value = {
        supplier_id: po.supplier.id, outlet_id: po.outlet.id, note: po.note ?? '',
        items: po.items.map((i) => ({ product_id: i.product_id, variant_id: i.variant_id, name: i.product_name, quantity: i.quantity, cost_price: i.cost_price })),
      }
    }
  } catch (e) {
    loadError.value = e.userMessage
  } finally {
    loading.value = false
  }
}

async function save(status) {
  saving.value = status
  errors.value = {}
  try {
    const po = await api.saveOrder({
      id: props.id ? Number(props.id) : undefined,
      ...form.value,
      status,
      note: form.value.note || null,
      items: form.value.items.map(({ product_id, variant_id, quantity, cost_price }) => ({ product_id, variant_id, quantity: Number(quantity) || 0, cost_price: Number(cost_price) || 0 })),
    })
    toast.success(status === 'ordered' ? `PO ${po.number} disimpan sebagai Dipesan.` : `Draf PO ${po.number} disimpan.`)
    router.push({ name: 'purchase-order', params: { id: po.id } })
  } catch (e) {
    if (e.fieldErrors) errors.value = e.fieldErrors
  } finally {
    saving.value = null
  }
}

function onSupplierCreated(supplier) {
  suppliers.value = [...suppliers.value, supplier].sort((a, b) => a.name.localeCompare(b.name))
  form.value.supplier_id = supplier.id
}

onMounted(load)
</script>

<template>
  <BackofficeLayout :title="id ? `Ubah PO ${number}` : 'Buat PO'">
    <div v-if="loading" class="flex flex-col gap-3"><Skeleton class="h-24" /><Skeleton class="h-64" /></div>
    <div v-else-if="loadError" class="rounded border border-border bg-surface">
      <EmptyState title="Data PO gagal dimuat." :description="loadError"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>
    <form v-else class="flex max-w-5xl flex-col gap-4" novalidate @submit.prevent="save('ordered')">
      <section class="grid gap-4 rounded border border-border bg-surface p-4 sm:grid-cols-3">
        <div class="flex items-end gap-2 sm:col-span-1">
          <Select v-model="form.supplier_id" class="flex-1" label="Supplier" placeholder="Pilih supplier" :options="supplierOptions" required :error="errors.supplier_id" />
          <Button variant="ghost" :class="errors.supplier_id && 'mb-6'" @click="supplierFormOpen = true">+ Baru</Button>
        </div>
        <Select v-if="outletOptions.length > 1 && !id" v-model="form.outlet_id" label="Outlet penerima" :options="outletOptions" :error="errors.outlet_id" />
        <Input v-model="form.note" label="Catatan" maxlength="255" placeholder="mis. kirim sebelum Jumat" :error="errors.note" :class="outletOptions.length > 1 && !id ? '' : 'sm:col-span-2'" />
      </section>

      <PoItemsEditor v-model="form.items" :errors="errors" />

      <div class="flex justify-end gap-2">
        <Button :disabled="!!saving" @click="router.back()">Batal</Button>
        <Button :disabled="!!saving && saving !== 'draft'" :loading="saving === 'draft'" loading-text="Menyimpan..." @click="save('draft')">Simpan Draf</Button>
        <Button type="submit" variant="primary" :disabled="!!saving && saving !== 'ordered'" :loading="saving === 'ordered'" loading-text="Menyimpan...">Simpan & Kirim</Button>
      </div>
    </form>

    <SupplierFormModal v-model:open="supplierFormOpen" @saved="onSupplierCreated" />
  </BackofficeLayout>
</template>
