<script setup>
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { usePurchaseApi } from '@/composables/usePurchaseApi'
import { useToastStore } from '@/stores/toast'

/** Tambah/ubah supplier. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  supplier: { type: Object, default: null },
})

const emit = defineEmits(['saved', 'delete'])

const api = usePurchaseApi()
const toast = useToastStore()
const form = ref({})
const errors = ref({})
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  errors.value = {}
  const s = props.supplier
  form.value = { id: s?.id, name: s?.name ?? '', contact_name: s?.contact_name ?? '', phone: s?.phone ?? '', email: s?.email ?? '', address: s?.address ?? '', note: s?.note ?? '' }
})

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const payload = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]))
    const saved = await api.saveSupplier(payload)
    toast.success(`Supplier ${saved.name} disimpan.`)
    open.value = false
    emit('saved', saved)
  } catch (e) {
    if (e.fieldErrors) errors.value = e.fieldErrors
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" :title="supplier ? `Ubah ${supplier.name}` : 'Tambah supplier'" size="md" :dismissible="!saving">
    <form id="supplier-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
      <Input v-model="form.name" label="Nama supplier" required maxlength="100" :error="errors.name" class="sm:col-span-2" />
      <Input v-model="form.contact_name" label="Nama kontak" maxlength="100" :error="errors.contact_name" />
      <Input v-model="form.phone" label="Nomor telepon" inputmode="tel" maxlength="20" :error="errors.phone" />
      <Input v-model="form.email" label="Email" type="email" maxlength="100" :error="errors.email" />
      <Input v-model="form.address" label="Alamat" maxlength="255" :error="errors.address" />
      <Input v-model="form.note" label="Catatan" maxlength="255" placeholder="mis. jadwal kirim Senin & Kamis" :error="errors.note" class="sm:col-span-2" />
    </form>
    <template #footer>
      <Button v-if="supplier" variant="danger-ghost" class="mr-auto" :disabled="saving" @click="emit('delete', supplier)">Hapus Supplier</Button>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="supplier-form" variant="primary" :loading="saving" loading-text="Menyimpan...">Simpan Supplier</Button>
    </template>
  </Modal>
</template>
