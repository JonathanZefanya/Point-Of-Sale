<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import http from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useSettingsStore } from '@/stores/settings'

/** Voucher & diskon manual untuk keranjang (F3.4, 2.5). Nilai akhir dihitung server lewat quote. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  outletId: { type: Number, required: true },
})

const cart = useCartStore()
const auth = useAuthStore()
const settings = useSettingsStore()

const code = ref('')
const codeError = ref('')
const checking = ref(false)
const type = ref('percent')
const value = ref(0)
const codeInput = ref(null)

const limit = computed(() => settings.settings?.discount?.manual_limit_percent ?? 10)
const isCashier = computed(() => auth.user?.role === 'cashier')
const valueError = computed(() => (type.value === 'percent' && value.value > 100 ? 'Diskon persen maksimal 100.' : ''))

watch(open, async (isOpen) => {
  if (!isOpen) return
  code.value = ''
  codeError.value = ''
  type.value = cart.manualDiscount?.type ?? 'percent'
  value.value = cart.manualDiscount?.value ?? 0
  await nextTick()
  if (!cart.voucherCode) codeInput.value?.focus()
})

async function applyVoucher() {
  if (!code.value.trim() || checking.value) return
  checking.value = true
  codeError.value = ''
  try {
    const { data } = await http.post('/vouchers/validate', { code: code.value.trim(), outlet_id: props.outletId }, { silent: true })
    cart.setVoucher(data.data.code)
    code.value = ''
  } catch (e) {
    codeError.value = e.fieldErrors?.voucher_code ?? e.fieldErrors?.code ?? e.userMessage
  } finally {
    checking.value = false
  }
}

function applyManual() {
  if (valueError.value) return
  cart.setManualDiscount({ type: type.value, value: Number(value.value) || 0 })
  open.value = false
}

function removeManual() {
  cart.setManualDiscount(null)
  value.value = 0
}

const segment = (active) => [
  'min-h-touch flex-1 rounded border px-3 font-medium',
  active ? 'border-primary bg-primary text-white' : 'border-border-strong bg-surface hover:bg-surface-muted',
]
</script>

<template>
  <Modal v-model:open="open" title="Diskon & voucher" size="sm">
    <div class="cashier-root flex flex-col gap-5">
      <section class="flex flex-col gap-2">
        <h3 class="font-semibold">Voucher</h3>
        <div v-if="cart.voucherCode" class="flex items-center justify-between gap-2 rounded border border-border px-3 py-2">
          <p>
            <span class="font-mono font-medium">{{ cart.voucherCode }}</span>
            <span v-if="cart.quote?.voucher" class="text-muted"> · {{ cart.quote.voucher.name }}</span>
          </p>
          <Button variant="danger-ghost" @click="cart.setVoucher(null)">Lepas</Button>
        </div>
        <form v-else class="flex items-start gap-2" novalidate @submit.prevent="applyVoucher">
          <Input ref="codeInput" v-model="code" class="flex-1 font-mono uppercase" label="Kode voucher" size="lg" maxlength="40" :error="codeError" placeholder="mis. HEMAT10" />
          <Button type="submit" size="lg" class="mt-6" :disabled="!code.trim()" :loading="checking" loading-text="Memeriksa...">Pakai</Button>
        </form>
      </section>

      <form id="manual-discount-form" class="flex flex-col gap-2" novalidate @submit.prevent="applyManual">
        <h3 class="font-semibold">Diskon manual</h3>
        <div class="flex gap-2" role="group" aria-label="Jenis diskon">
          <button type="button" :class="segment(type === 'percent')" :aria-pressed="type === 'percent'" @click="type = 'percent'">Persen (%)</button>
          <button type="button" :class="segment(type === 'amount')" :aria-pressed="type === 'amount'" @click="type = 'amount'">Rupiah (Rp)</button>
        </div>
        <Input v-if="type === 'percent'" v-model.number="value" type="number" min="0" max="100" inputmode="numeric" label="Diskon (%)" size="lg" :error="valueError" />
        <MoneyInput v-else v-model="value" label="Potongan" size="lg" />
        <p v-if="isCashier" class="text-sm text-muted">Diskon di atas {{ limit }}% dari total butuh PIN pemilik/manajer saat bayar.</p>
        <Button v-if="cart.manualDiscount" variant="danger-ghost" class="self-start" @click="removeManual">Hapus diskon manual</Button>
      </form>
    </div>

    <template #footer>
      <Button size="lg" @click="open = false">Tutup</Button>
      <Button type="submit" form="manual-discount-form" variant="primary" size="lg" :disabled="!!valueError">Terapkan Diskon</Button>
    </template>
  </Modal>
</template>
