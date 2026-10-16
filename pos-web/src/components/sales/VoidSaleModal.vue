<script setup>
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { useSaleApi } from '@/composables/useSaleApi'
import { formatRupiah } from '@/lib/format'

/**
 * Batalkan transaksi (F5): alasan wajib → PIN supervisor → stok dikembalikan.
 * Tidak bisa dibatalkan setelahnya, jadi modal ini juga berfungsi sebagai konfirmasi.
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  sale: { type: Object, default: null },
})

const emit = defineEmits(['voided'])

const api = useSaleApi()
const reason = ref('')
const pin = ref('')
const errors = ref({})
const message = ref('')
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  reason.value = ''
  pin.value = ''
  errors.value = {}
  message.value = ''
})

async function submit() {
  const next = {}
  if (reason.value.trim().length < 3) next.reason = 'Tulis alasan pembatalan, mis. "Pelanggan batal beli".'
  if (!/^\d{6}$/.test(pin.value)) next.supervisor_pin = 'Minta pemilik/manajer memasukkan PIN 6 digit.'
  errors.value = next
  if (Object.keys(next).length) return

  saving.value = true
  message.value = ''
  try {
    const sale = await api.void(props.sale.id, reason.value.trim(), pin.value)
    open.value = false
    emit('voided', sale)
  } catch (e) {
    pin.value = ''
    errors.value = e.fieldErrors ?? {}
    if (!e.fieldErrors) message.value = e.userMessage
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" :title="sale ? `Batalkan ${sale.number}?` : 'Batalkan transaksi'" size="sm" :dismissible="!saving">
    <form v-if="sale" id="void-form" class="cashier-root flex flex-col gap-4" novalidate @submit.prevent="submit">
      <p>
        Transaksi {{ formatRupiah(sale.grand_total) }} dibatalkan, stok dikembalikan, dan uang dikembalikan ke pelanggan.
        Tindakan ini tidak bisa dibatalkan.
      </p>
      <Input v-model="reason" label="Alasan" size="lg" required maxlength="500" placeholder="mis. Pelanggan batal beli" :error="errors.reason" @input="errors.reason = ''" />
      <Input
        v-model="pin"
        label="PIN supervisor"
        type="password"
        size="lg"
        inputmode="numeric"
        maxlength="6"
        autocomplete="off"
        hint="Diisi oleh pemilik atau manajer outlet."
        :error="errors.supervisor_pin"
        @input="errors.supervisor_pin = ''"
      />
      <p v-if="message" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ message }}</p>
    </form>
    <template #footer>
      <Button size="lg" :disabled="saving" @click="open = false">Kembali</Button>
      <Button type="submit" form="void-form" variant="danger" size="lg" :loading="saving" loading-text="Membatalkan...">Batalkan Transaksi</Button>
    </template>
  </Modal>
</template>
