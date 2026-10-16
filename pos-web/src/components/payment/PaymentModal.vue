<script setup>
import { storeToRefs } from 'pinia'
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { useOfflineCheckout } from '@/composables/useOfflineCheckout'
import { useOnlineStatus } from '@/composables/useOnlineStatus'
import { usePaymentDraft } from '@/composables/usePaymentDraft'
import { newIdempotencyKey, useSaleApi } from '@/composables/useSaleApi'
import http from '@/lib/http'
import { formatRupiah } from '@/lib/format'
import { isNetworkError } from '@/lib/offlineDb'
import { PAYMENT_METHODS } from '@/lib/paymentMethods'
import { useCartStore } from '@/stores/cart'
import { useSettingsStore } from '@/stores/settings'
import PaymentSummary from './PaymentSummary.vue'
import PaymentRowEditor from './PaymentRowEditor.vue'

/**
 * Modal pembayaran (F3 langkah 5–6): Bayar → pilih metode/nominal → Selesai.
 * "Selesai" aktif hanya bila sisa = 0. Satu idempotency key per sesi modal: kirim ulang setelah
 * koneksi putus tidak membuat transaksi dobel.
 * Offline (FE-3.1): transaksi masuk antrian perangkat dengan key yang sama → disinkron kemudian tanpa dobel.
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  outletId: { type: Number, required: true },
})

const emit = defineEmits(['paid', 'stock-error'])

const cart = useCartStore()
const { grandTotal } = storeToRefs(cart)
const api = useSaleApi()
const settingsStore = useSettingsStore()
const { online } = useOnlineStatus()
const offline = useOfflineCheckout()

// Poin hanya bisa dipakai bila loyalty aktif dan pelanggan punya saldo (2.4)
const points = computed(() => {
  const loyalty = settingsStore.settings?.loyalty
  const balance = cart.customer?.points ?? 0
  // Poin butuh saldo terkini di server → tidak tersedia saat offline
  return online.value && loyalty?.enabled && balance > 0 ? { balance, value: loyalty.point_value } : null
})
const methods = computed(() => PAYMENT_METHODS.filter((m) => m.value !== 'points' || points.value))
const draft = usePaymentDraft(grandTotal, points)

const submitting = ref(false)
const error = ref('')
// Diskon manual kasir di atas batas → PIN pemilik/manajer (2.5)
const supervisorPin = ref('')
const pinError = ref('')
const pinMissing = computed(() => cart.needsSupervisor && supervisorPin.value.length !== 6)
let idempotencyKey = null

// QRIS dinamis tersedia bila server punya kredensial Midtrans (3.5)
const qrisDynamic = ref(false)
http.get('/payments/gateway', { silent: true }).then((r) => (qrisDynamic.value = r.data.data.qris_dynamic)).catch(() => {})

watch(open, (isOpen) => {
  if (!isOpen) return
  draft.reset()
  error.value = ''
  supervisorPin.value = ''
  pinError.value = ''
  idempotencyKey = newIdempotencyKey()
})

async function finish() {
  if (!draft.canFinish.value || submitting.value) return
  if (pinMissing.value) {
    pinError.value = 'Masukkan 6 digit PIN pemilik/manajer untuk menyetujui diskon.'
    return
  }
  submitting.value = true
  error.value = ''
  try {
    if (!online.value) return await saveOffline()
    const sale = await api.checkout(
      {
        outlet_id: props.outletId,
        items: cart.payloadItems,
        payments: draft.payload.value,
        held_sale_id: cart.heldSaleId,
        customer_id: cart.customer?.id ?? null,
        order_type: cart.effectiveOrderType,
        voucher_code: cart.voucherCode,
        manual_discount: cart.manualDiscount,
        supervisor_pin: cart.needsSupervisor ? supervisorPin.value : null,
      },
      idempotencyKey,
    )
    open.value = false
    emit('paid', sale)
  } catch (e) {
    // Server tidak terjangkau di tengah pembayaran → simpan offline dengan key yang sama
    if (isNetworkError(e) && !offline.blocker.value) return await saveOffline()
    if (e.status === 409 && e.response?.data?.items) {
      // Stok kurang: keranjang tetap utuh, item bermasalah ditandai merah (F3.9)
      open.value = false
      emit('stock-error', e.response.data)
    } else {
      const serverTotal = e.response?.data?.grand_total
      pinError.value = e.fieldErrors?.supervisor_pin ?? ''
      error.value = pinError.value ? '' : (e.fieldErrors?.payments ?? e.fieldErrors?.voucher_code ?? e.userMessage)
      if (serverTotal && serverTotal !== grandTotal.value) error.value += ` Total menurut server ${formatRupiah(serverTotal)}.`
    }
  } finally {
    submitting.value = false
  }
}

async function saveOffline() {
  if (offline.blocker.value) {
    error.value = offline.blocker.value
    return
  }
  const sale = await offline.save(idempotencyKey, props.outletId, draft.payload.value)
  open.value = false
  emit('paid', sale)
}
</script>

<template>
  <Modal v-model:open="open" title="Pembayaran" size="md" :dismissible="!submitting">
    <form id="payment-form" class="cashier-root flex flex-col gap-4" novalidate @submit.prevent="finish">
      <div class="text-center">
        <p class="text-muted">Total bayar</p>
        <p class="text-[40px] font-bold leading-tight tabular-nums">{{ formatRupiah(grandTotal) }}</p>
      </div>

      <PaymentRowEditor
        v-if="draft.active.value"
        :row="draft.active.value"
        :due="draft.dueForActive.value"
        :methods="methods"
        :points="points"
        :customer-name="cart.customer?.name ?? ''"
        :outlet-id="outletId"
        :qris-dynamic="qrisDynamic && online"
        @set-method="draft.setMethod"
      />

      <PaymentSummary
        :payments="draft.payments.value"
        :active-index="draft.activeIndex.value"
        :paid="draft.paid.value"
        :remaining="draft.remaining.value"
        :change="draft.change.value"
        @select="draft.activeIndex.value = $event"
        @remove="draft.removeAt"
      />

      <Button v-if="draft.remaining.value > 0 && draft.paid.value > 0" variant="ghost" size="lg" @click="draft.addMethod">
        + Tambah metode lain untuk sisa {{ formatRupiah(draft.remaining.value) }}
      </Button>

      <Input
        v-if="cart.needsSupervisor"
        v-model="supervisorPin"
        type="password"
        inputmode="numeric"
        maxlength="6"
        autocomplete="off"
        size="lg"
        label="PIN pemilik/manajer"
        hint="Diskon manual melebihi batas kasir. Minta pemilik/manajer memasukkan PIN."
        :error="pinError"
      />

      <p v-if="!online" class="rounded border border-warning/30 bg-warning-soft px-3 py-2 text-warning" role="status">
        Offline: transaksi disimpan di perangkat dan dikirim otomatis saat koneksi kembali.
      </p>

      <p v-if="draft.problem.value || error" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">
        {{ draft.problem.value || error }}
      </p>
    </form>

    <template #footer>
      <Button size="lg" :disabled="submitting" @click="open = false">Kembali</Button>
      <Button
        type="submit"
        form="payment-form"
        variant="primary"
        size="lg"
        shortcut="Enter"
        :disabled="!draft.canFinish.value"
        :loading="submitting"
        loading-text="Memproses..."
      >
        Selesai
      </Button>
    </template>
  </Modal>
</template>
