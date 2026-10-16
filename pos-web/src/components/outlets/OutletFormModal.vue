<script setup>
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import Textarea from '@/components/ui/Textarea.vue'
import http from '@/lib/http'
import { useToastStore } from '@/stores/toast'

/** Tambah/ubah outlet (2.8). Kode outlet muncul di nomor transaksi, mis. JKT01-20261015-0042. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  outlet: { type: Object, default: null },
})

const emit = defineEmits(['saved', 'delete'])

const toast = useToastStore()
const form = ref({})
const errors = ref({})
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  errors.value = {}
  const o = props.outlet
  form.value = {
    code: o?.code ?? '', name: o?.name ?? '', address: o?.address ?? '', phone: o?.phone ?? '',
    tax_rate: o?.tax_rate ?? 0, service_charge_rate: o?.service_charge_rate ?? 0,
    receipt_header: o?.receipt_header ?? '', receipt_footer: o?.receipt_footer ?? '',
  }
})

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const payload = Object.fromEntries(Object.entries(form.value).map(([k, v]) => [k, v === '' ? null : v]))
    const { data } = props.outlet ? await http.put(`/outlets/${props.outlet.id}`, payload) : await http.post('/outlets', payload)
    toast.success(`Outlet ${data.data.name} disimpan.`)
    open.value = false
    emit('saved', data.data)
  } catch (e) {
    if (e.fieldErrors) errors.value = e.fieldErrors
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" :title="outlet ? `Ubah ${outlet.name}` : 'Tambah outlet'" size="md" :dismissible="!saving">
    <form id="outlet-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
      <Input
        v-model="form.code"
        label="Kode outlet"
        required
        maxlength="10"
        class="font-mono uppercase"
        placeholder="mis. JKT02"
        hint="Dipakai di nomor transaksi. Huruf besar & angka."
        :error="errors.code"
      />
      <Input v-model="form.name" label="Nama outlet" required maxlength="100" placeholder="mis. Kopi Senja Kemang" :error="errors.name" />
      <Input v-model="form.address" label="Alamat" maxlength="255" :error="errors.address" class="sm:col-span-2" />
      <Input v-model="form.phone" label="Telepon" inputmode="tel" maxlength="30" :error="errors.phone" />
      <div />
      <Input v-model.number="form.tax_rate" type="number" min="0" max="100" step="0.01" label="Pajak (%)" hint="Berlaku bila pajak diaktifkan di Pengaturan." :error="errors.tax_rate" />
      <Input v-model.number="form.service_charge_rate" type="number" min="0" max="100" step="0.01" label="Service charge (%)" :error="errors.service_charge_rate" />
      <Textarea v-model="form.receipt_header" label="Header struk" :rows="2" maxlength="500" hint="Kosongkan untuk memakai nama & alamat outlet." :error="errors.receipt_header" />
      <Textarea v-model="form.receipt_footer" label="Footer struk" :rows="2" maxlength="500" placeholder="mis. Terima kasih, sampai jumpa lagi" :error="errors.receipt_footer" />
    </form>
    <template #footer>
      <Button v-if="outlet" variant="danger-ghost" class="mr-auto" :disabled="saving" @click="emit('delete', outlet)">Hapus Outlet</Button>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="outlet-form" variant="primary" :loading="saving" loading-text="Menyimpan...">Simpan Outlet</Button>
    </template>
  </Modal>
</template>
