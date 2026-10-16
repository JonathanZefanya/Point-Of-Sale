<script setup>
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Select from '@/components/ui/Select.vue'
import { CUSTOMER_GROUPS, useCustomerApi } from '@/composables/useCustomerApi'
import { useToastStore } from '@/stores/toast'

/** Tambah/ubah pelanggan di back office. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  customer: { type: Object, default: null },
})

const emit = defineEmits(['saved', 'delete'])

const api = useCustomerApi()
const toast = useToastStore()
const form = ref({})
const errors = ref({})
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  errors.value = {}
  const c = props.customer
  form.value = { id: c?.id, name: c?.name ?? '', phone: c?.phone ?? '', email: c?.email ?? '', birth_date: c?.birth_date ?? '', group: c?.group ?? 'member', note: c?.note ?? '' }
})

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const payload = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]))
    const saved = await api.save(payload)
    toast.success(`Pelanggan ${saved.name} disimpan.`)
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
  <Modal v-model:open="open" :title="customer ? `Ubah ${customer.name}` : 'Tambah pelanggan'" size="md" :dismissible="!saving">
    <form id="customer-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
      <Input v-model="form.name" label="Nama" required :error="errors.name" />
      <Input v-model="form.phone" label="Nomor HP" inputmode="tel" placeholder="081234567890" :error="errors.phone" />
      <Input v-model="form.email" label="Email" type="email" :error="errors.email" />
      <Input v-model="form.birth_date" label="Tanggal lahir" type="date" :error="errors.birth_date" />
      <Select v-model="form.group" label="Grup" :options="CUSTOMER_GROUPS" :error="errors.group" />
      <Input v-model="form.note" label="Catatan" maxlength="255" :error="errors.note" />
    </form>
    <template #footer>
      <Button v-if="customer" variant="danger-ghost" class="mr-auto" :disabled="saving" @click="emit('delete', customer)">Hapus Pelanggan</Button>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="customer-form" variant="primary" :loading="saving" loading-text="Menyimpan...">Simpan Pelanggan</Button>
    </template>
  </Modal>
</template>
