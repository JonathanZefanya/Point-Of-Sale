<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import CashierLayout from '@/layouts/CashierLayout.vue'
import { formatRupiah } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { useDeviceStore } from '@/stores/device'
import { useShiftStore } from '@/stores/shift'

/** F2: hitung uang di laci → modal awal (default sisa kas shift sebelumnya) → Mulai Shift. */
const auth = useAuthStore()
const device = useDeviceStore()
const shiftStore = useShiftStore()
const router = useRouter()

const openingCash = ref(null)
const error = ref('')
const starting = ref(false)
const loggingOut = ref(false)

const outletCode = computed(() => device.outletCode ?? '')

async function start() {
  if (openingCash.value === null) {
    error.value = 'Isi modal awal. Tulis 0 jika laci kosong.'
    return
  }
  starting.value = true
  try {
    await shiftStore.open(openingCash.value)
    router.replace({ name: 'cashier' })
  } catch (e) {
    error.value = e.fieldErrors?.opening_cash ?? ''
  } finally {
    starting.value = false
  }
}

async function logout() {
  loggingOut.value = true
  await auth.logout()
  router.replace({ name: 'cashier-login' })
}

onMounted(async () => {
  await shiftStore.ensureLoaded().catch(() => {})
  openingCash.value = shiftStore.suggestedOpeningCash
})
</script>

<template>
  <CashierLayout :outlet-code="outletCode" :cashier-name="auth.user?.name ?? ''">
    <template #header-actions>
      <Button size="lg" :loading="loggingOut" loading-text="Keluar..." @click="logout">Keluar</Button>
    </template>

    <div class="flex w-full justify-center px-4 pt-[8vh]">
      <div v-if="shiftStore.outletError" class="h-fit w-full max-w-lg rounded border border-border bg-surface">
        <EmptyState title="Shift tidak bisa dibuka." :description="shiftStore.outletError">
          <Button size="lg" variant="primary" @click="logout">Masuk dengan Akun Lain</Button>
        </EmptyState>
      </div>

      <form v-else class="flex w-full max-w-sm flex-col gap-5" novalidate @submit.prevent="start">
        <div>
          <h1 class="text-xl font-semibold">Buka shift</h1>
          <p class="mt-1 text-muted">Hitung uang di laci kasir, lalu isi jumlahnya sebagai modal awal.</p>
        </div>

        <MoneyInput
          v-model="openingCash"
          label="Modal awal"
          size="lg"
          autofocus
          :error="error"
          :hint="shiftStore.suggestedOpeningCash ? `Sisa kas shift sebelumnya ${formatRupiah(shiftStore.suggestedOpeningCash)}. Ubah jika berbeda.` : 'Isi 0 jika laci kosong.'"
          @input="error = ''"
        />

        <Button type="submit" variant="primary" size="lg" block :loading="starting" loading-text="Membuka shift...">Mulai Shift</Button>
      </form>
    </div>
  </CashierLayout>
</template>
