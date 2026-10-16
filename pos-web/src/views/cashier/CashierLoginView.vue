<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CashierPicker from '@/components/auth/CashierPicker.vue'
import OutletCodeForm from '@/components/auth/OutletCodeForm.vue'
import PinPad from '@/components/auth/PinPad.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useCountdown } from '@/composables/useCountdown'
import { useOnlineStatus } from '@/composables/useOnlineStatus'
import http from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useDeviceStore } from '@/stores/device'

/** F1.2: pilih nama kasir → PIN 6 digit → layar kasir. PIN salah 5× → kunci dengan hitung mundur. */
const auth = useAuthStore()
const device = useDeviceStore()
const router = useRouter()
const { online } = useOnlineStatus()
const lock = useCountdown()

const editingOutlet = ref(!device.outletCode)
const users = ref([])
const loadingUsers = ref(false)
const loadError = ref('')
const selected = ref(null)
const pinError = ref('')
const verifying = ref(false)
const pinPad = ref(null)

const step = computed(() => {
  if (editingOutlet.value) return 'outlet'
  return selected.value ? 'pin' : 'pick'
})

async function loadUsers() {
  loadingUsers.value = true
  loadError.value = ''
  try {
    const { data } = await http.get('/auth/pin-users', { params: { outlet_code: device.outletCode }, silent: true })
    users.value = data.data
  } catch (error) {
    users.value = []
    loadError.value = error.userMessage
  } finally {
    loadingUsers.value = false
  }
}

function saveOutlet(code) {
  device.setOutletCode(code)
  editingOutlet.value = false
  selected.value = null
  loadUsers()
}

function selectUser(user) {
  selected.value = user
  pinError.value = ''
  lock.stop()
}

function backToPicker() {
  selected.value = null
  pinError.value = ''
  lock.stop()
}

async function submitPin(pin) {
  verifying.value = true
  pinError.value = ''
  try {
    await auth.pinLogin(selected.value.id, pin)
    router.replace({ name: 'cashier' })
  } catch (error) {
    if (error.status === 429 && error.response?.data?.retry_after) {
      lock.start(error.response.data.retry_after)
      pinError.value = error.userMessage
    } else if (error.fieldErrors?.user_id) {
      // Kasir dihapus/tidak punya PIN lagi sejak daftar dimuat
      backToPicker()
      loadError.value = error.fieldErrors.user_id
      loadUsers()
    } else {
      pinError.value = error.fieldErrors?.pin ?? error.userMessage
    }
    pinPad.value?.clear()
  } finally {
    verifying.value = false
  }
}

// Setelah masa kunci habis, hapus pesan agar kasir tahu sudah boleh mencoba lagi
watch(lock.active, (active, wasActive) => {
  if (wasActive && !active) pinError.value = ''
})

watch(step, async (value) => {
  if (value === 'pick') {
    await nextTick()
    document.querySelector('[data-picker] button')?.focus()
  }
})

onMounted(() => {
  if (device.outletCode) loadUsers()
})
</script>

<template>
  <div class="cashier-root flex min-h-screen flex-col bg-bg">
    <header class="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border bg-surface px-4">
      <div class="flex items-center gap-2 text-muted">
        <span class="font-semibold text-text">POS</span>
        <template v-if="device.outletCode">
          <span aria-hidden="true">·</span>
          <span>Outlet <span class="font-mono font-medium text-text">{{ device.outletCode }}</span></span>
        </template>
        <span aria-hidden="true">·</span>
        <span class="inline-flex items-center gap-1.5" :class="online ? 'text-success' : 'text-danger'">
          <span class="size-2 rounded-full bg-current" aria-hidden="true" />
          {{ online ? 'Online' : 'Offline' }}
        </span>
      </div>
      <div class="flex items-center gap-2">
        <Button v-if="step !== 'outlet'" variant="ghost" size="lg" @click="editingOutlet = true">Ganti Outlet</Button>
        <RouterLink
          :to="{ name: 'login' }"
          class="inline-flex min-h-touch items-center rounded px-3 text-muted hover:bg-surface-muted hover:text-text"
        >
          Login back office
        </RouterLink>
      </div>
    </header>

    <main class="flex flex-1 justify-center px-4 pt-[8vh]">
      <OutletCodeForm v-if="step === 'outlet'" :initial-code="device.outletCode ?? ''" @save="saveOutlet" />

      <template v-else-if="step === 'pick'">
        <div v-if="loadError" class="w-full max-w-lg rounded border border-border bg-surface">
          <EmptyState title="Daftar kasir tidak bisa dimuat." :description="loadError">
            <Button variant="primary" size="lg" @click="loadUsers">Coba Lagi</Button>
            <Button size="lg" @click="editingOutlet = true">Ganti Kode Outlet</Button>
          </EmptyState>
        </div>
        <div v-else-if="!loadingUsers && !users.length" class="w-full max-w-lg rounded border border-border bg-surface">
          <EmptyState
            :title="`Belum ada kasir dengan PIN di outlet ${device.outletCode}.`"
            description="Minta pemilik atau manajer menambahkan kasir dan PIN-nya di menu Karyawan, lalu muat ulang."
          >
            <Button variant="primary" size="lg" @click="loadUsers">Muat Ulang</Button>
          </EmptyState>
        </div>
        <div v-else data-picker class="flex w-full justify-center">
          <CashierPicker :users="users" :loading="loadingUsers" @select="selectUser" />
        </div>
      </template>

      <div v-else class="flex w-full max-w-xs flex-col items-center gap-5">
        <div class="text-center">
          <p class="text-muted">Masukkan PIN 6 digit untuk</p>
          <p class="text-2xl font-semibold">{{ selected.name }}</p>
        </div>

        <PinPad ref="pinPad" :disabled="lock.active.value" :busy="verifying" @submit="submitPin" @cancel="backToPicker" />

        <p v-if="verifying" class="text-muted" role="status">Memeriksa PIN...</p>
        <div v-else-if="pinError" class="w-full rounded border border-danger/30 bg-danger-soft px-3 py-2 text-center text-danger" role="alert">
          <p>{{ pinError }}</p>
          <p v-if="lock.active.value" class="mt-1 font-mono text-lg font-semibold tabular-nums">{{ lock.label.value }}</p>
        </div>

        <Button variant="ghost" size="lg" block @click="backToPicker">Bukan {{ selected.name }}? Pilih nama lain</Button>
      </div>
    </main>
  </div>
</template>
