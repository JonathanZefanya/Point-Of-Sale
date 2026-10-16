<script setup>
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { useTableApi } from '@/composables/useTableApi'
import { useToastStore } from '@/stores/toast'

/** Tambah/ubah meja (F&B 3.3). */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  table: { type: Object, default: null },
  outletId: { type: Number, default: null },
})

const emit = defineEmits(['saved', 'delete'])

const api = useTableApi()
const toast = useToastStore()
const form = ref({})
const errors = ref({})
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  errors.value = {}
  const t = props.table
  form.value = { id: t?.id, name: t?.name ?? '', area: t?.area ?? '', capacity: t?.capacity ?? 4, is_active: t?.is_active ?? true }
})

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const payload = { ...form.value, area: form.value.area || null, ...(form.value.id ? {} : { outlet_id: props.outletId }) }
    const saved = await api.save(payload)
    toast.success(`${saved.name} disimpan.`)
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
  <Modal v-model:open="open" :title="table ? `Ubah ${table.name}` : 'Tambah meja'" size="sm" :dismissible="!saving">
    <form id="table-form" class="grid gap-4" novalidate @submit.prevent="save">
      <Input v-model="form.name" label="Nama meja" required maxlength="30" placeholder="mis. Meja 5 atau T1" :error="errors.name" />
      <Input v-model="form.area" label="Area" maxlength="40" placeholder="mis. Indoor, Teras, Lantai 2" hint="Meja dikelompokkan per area di layar kasir." :error="errors.area" />
      <Input v-model.number="form.capacity" type="number" min="1" max="100" label="Jumlah kursi" :error="errors.capacity" />
      <Checkbox v-model="form.is_active" label="Meja dipakai" description="Matikan untuk menyembunyikan meja dari layar kasir tanpa menghapusnya." />
    </form>
    <template #footer>
      <Button v-if="table" variant="danger-ghost" class="mr-auto" :disabled="saving" @click="emit('delete', table)">Hapus Meja</Button>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="table-form" variant="primary" :loading="saving" loading-text="Menyimpan...">Simpan Meja</Button>
    </template>
  </Modal>
</template>
