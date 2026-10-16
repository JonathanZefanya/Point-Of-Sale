<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Select from '@/components/ui/Select.vue'
import { useProductApi } from '@/composables/useProductApi'
import { useStockApi } from '@/composables/useStockApi'
import { useToastStore } from '@/stores/toast'

/** Mulai stock opname (F8): pilih kategori atau semua produk → daftar hitung dibuat dari stok saat ini. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  outlet: { type: Object, default: null }, // { id, code, name }
})

const api = useStockApi()
const toast = useToastStore()
const router = useRouter()
const categories = ref([])
const categoryId = ref('')
const note = ref('')
const saving = ref(false)
const error = ref('')

const options = computed(() => [{ value: '', label: 'Semua produk' }, ...categories.value.map((c) => ({ value: c.id, label: c.name }))])

async function start() {
  saving.value = true
  error.value = ''
  try {
    const opname = await api.startOpname({ outlet_id: props.outlet?.id, category_id: categoryId.value || null, note: note.value || null })
    open.value = false
    router.push({ name: 'stock-opname', params: { id: opname.id } })
  } catch (e) {
    if (e.status === 409 && e.response?.data?.opname_id) {
      // Masih ada draf: langsung buka draf tersebut
      toast.info(e.userMessage)
      open.value = false
      router.push({ name: 'stock-opname', params: { id: e.response.data.opname_id } })
    } else {
      error.value = e.userMessage
    }
  } finally {
    saving.value = false
  }
}

onMounted(() => useProductApi().categories().then((c) => (categories.value = c)).catch(() => {}))
</script>

<template>
  <Modal v-model:open="open" title="Mulai stock opname" size="sm" :dismissible="!saving">
    <form id="start-opname-form" class="flex flex-col gap-4" novalidate @submit.prevent="start">
      <p v-if="outlet">Outlet: <span class="font-medium">{{ outlet.code }} · {{ outlet.name }}</span></p>
      <Select v-model="categoryId" label="Produk yang dihitung" :options="options" hint="Daftar hitung berisi semua produk yang dilacak stoknya." />
      <Input v-model="note" label="Catatan (opsional)" maxlength="255" placeholder="mis. Opname akhir bulan Oktober" />
      <p v-if="error" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ error }}</p>
    </form>
    <template #footer>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="start-opname-form" variant="primary" :loading="saving" loading-text="Menyiapkan...">Mulai Opname</Button>
    </template>
  </Modal>
</template>
