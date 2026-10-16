<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ShiftCountSummary from '@/components/shift/ShiftCountSummary.vue'
import ShiftReport from '@/components/shift/ShiftReport.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import Input from '@/components/ui/Input.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import CashierLayout from '@/layouts/CashierLayout.vue'
import http from '@/lib/http'
import { formatRupiah } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { useDeviceStore } from '@/stores/device'
import { useOfflineQueueStore } from '@/stores/offlineQueue'
import { useShiftStore } from '@/stores/shift'
import { useToastStore } from '@/stores/toast'

/**
 * F6: ringkasan → input kas fisik (tanpa melihat angka sistem) → konfirmasi →
 * bila ada selisih: tampilkan selisih + catatan wajib → laporan shift → cetak → keluar.
 */
const auth = useAuthStore()
const device = useDeviceStore()
const shiftStore = useShiftStore()
const router = useRouter()
const toast = useToastStore()

const step = ref('count') // count | mismatch | done
const report = ref(null)
const actualCash = ref(null)
const note = ref('')
const errors = ref({})
const mismatch = ref(null) // { expected_cash, actual_cash, difference }
const confirmOpen = ref(false)
const closing = ref(false)

const shift = computed(() => shiftStore.shift)
// Transaksi offline shift ini harus tersinkron dulu, agar kas yang diharapkan sudah lengkap (FE-3.1)
const queue = useOfflineQueueStore()
const unsynced = computed(() => (shift.value ? queue.pendingForShift(shift.value.id) : 0))

function startClose() {
  if (unsynced.value) {
    errors.value = { actual_cash: `Masih ada ${unsynced.value} transaksi offline belum tersinkron. Sambungkan internet dan tunggu sampai sinkron selesai.` }
    return
  }
  if (actualCash.value === null) {
    errors.value = { actual_cash: 'Isi jumlah uang yang ada di laci. Tulis 0 jika kosong.' }
    return
  }
  confirmOpen.value = true
}

async function submit() {
  closing.value = true
  try {
    const result = await shiftStore.close(actualCash.value, note.value.trim() || null)
    report.value = result.report
    confirmOpen.value = false
    step.value = 'done'
  } catch (error) {
    confirmOpen.value = false
    if (error.response?.data?.difference !== undefined) {
      mismatch.value = error.response.data
      step.value = 'mismatch'
    }
    if (error.fieldErrors) errors.value = error.fieldErrors
    else toast.error(error.userMessage) // close() memanggil API secara silent
  } finally {
    closing.value = false
  }
}

function recount() {
  step.value = 'count'
  mismatch.value = null
  note.value = ''
  errors.value = {}
}

function printReport() {
  window.print()
}

async function finish() {
  await auth.logout()
  router.replace({ name: 'cashier-login' })
}

onMounted(async () => {
  if (!shift.value) return router.replace({ name: 'cashier' })
  const { data } = await http.get(`/shifts/${shift.value.id}`)
  report.value = data.report
})
</script>

<template>
  <CashierLayout :outlet-code="device.outletCode ?? ''" :cashier-name="auth.user?.name ?? ''" :shift-opened-at="step === 'done' ? null : shift?.opened_at">
    <template v-if="step !== 'done'" #header-actions>
      <Button size="lg" @click="router.push({ name: 'cashier' })">Kembali ke Kasir</Button>
    </template>

    <div class="flex w-full justify-center overflow-y-auto px-4 py-6">
      <div v-if="step === 'count' && shift" class="flex w-full max-w-md flex-col gap-4">
        <h1 class="text-xl font-semibold">Tutup shift</h1>
        <ShiftCountSummary :shift="shift" :report="report" />
        <MoneyInput
          v-model="actualCash"
          label="Uang tunai di laci (hitung fisik)"
          size="lg"
          autofocus
          hint="Hitung semua uang kertas dan koin di laci, termasuk modal awal."
          :error="errors.actual_cash"
          @input="errors.actual_cash = ''"
        />
        <Button variant="primary" size="lg" block @click="startClose">Tutup Shift</Button>
      </div>

      <div v-else-if="step === 'mismatch'" class="flex w-full max-w-md flex-col gap-4">
        <h1 class="text-xl font-semibold">Ada selisih kas</h1>
        <dl class="grid grid-cols-[1fr_auto] gap-x-4 gap-y-1 rounded border border-border bg-surface p-4">
          <dt class="text-muted">Kas seharusnya (sistem)</dt><dd class="num">{{ formatRupiah(mismatch.expected_cash) }}</dd>
          <dt class="text-muted">Kas fisik</dt><dd class="num">{{ formatRupiah(mismatch.actual_cash) }}</dd>
          <dt class="font-semibold">Selisih</dt>
          <dd :class="['num text-2xl font-bold', mismatch.difference < 0 ? 'text-danger' : 'text-warning']">
            {{ mismatch.difference > 0 ? '+' : '' }}{{ formatRupiah(mismatch.difference) }}
          </dd>
        </dl>
        <Input
          v-model="note"
          label="Catatan penyebab selisih"
          size="lg"
          required
          maxlength="500"
          placeholder="mis. Uang kembalian kurang Rp5.000 ke pelanggan"
          :error="errors.note"
          @input="errors.note = ''"
        />
        <div class="flex gap-2">
          <Button size="lg" class="flex-1" :disabled="closing" @click="recount">Hitung Ulang</Button>
          <Button variant="primary" size="lg" class="flex-1" :loading="closing" loading-text="Menutup..." @click="submit">
            Tutup dengan Selisih
          </Button>
        </div>
      </div>

      <div v-else-if="step === 'done'" class="flex flex-col items-center gap-4">
        <p class="text-lg font-semibold">Shift ditutup.</p>
        <ShiftReport :shift="shift" :report="report" />
        <div class="flex w-full max-w-sm gap-2">
          <Button size="lg" class="flex-1" @click="printReport">Cetak Laporan</Button>
          <Button variant="primary" size="lg" class="flex-1" @click="finish">Selesai &amp; Keluar</Button>
        </div>
      </div>
    </div>

    <ConfirmModal
      v-model:open="confirmOpen"
      title="Tutup shift sekarang?"
      confirm-label="Tutup Shift"
      busy-label="Menutup..."
      tone="primary"
      :busy="closing"
      @confirm="submit"
    >
      <p>Kas fisik yang Anda hitung: <strong>{{ formatRupiah(actualCash ?? 0) }}</strong>.</p>
      <p class="text-muted">Setelah ditutup, tidak bisa ada transaksi lagi di shift ini.</p>
    </ConfirmModal>
  </CashierLayout>
</template>
