import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import http from '@/lib/http'
import { useDeviceStore } from '@/stores/device'
import { useOutletStore } from '@/stores/outlet'

/**
 * Shift kasir yang sedang terbuka di outlet perangkat ini (F2 & F6).
 * Route guard memakai store ini: kasir tanpa shift terbuka diarahkan ke "Buka Shift".
 */
export const useShiftStore = defineStore('shift', () => {
  const shift = ref(null)
  const suggestedOpeningCash = ref(0)
  const loaded = ref(false)
  const outletError = ref('')

  const isOpen = computed(() => shift.value?.status === 'open')

  /** Outlet perangkat kasir (kode di perangkat) → id; tanpa kode pakai outlet terpilih di back office. */
  async function resolveOutletId() {
    const outletStore = useOutletStore()
    const device = useDeviceStore()
    await outletStore.ensureLoaded()

    if (!device.outletCode) return outletStore.current?.id ?? null
    const outlet = outletStore.outlets.find((o) => o.code === device.outletCode)
    if (!outlet) {
      outletError.value = `Anda tidak ditugaskan di outlet ${device.outletCode}. Minta pemilik menambahkan outlet ini ke akun Anda.`
      return null
    }
    return outlet.id
  }

  const outletId = ref(null)

  async function ensureLoaded() {
    if (loaded.value) return shift.value
    outletError.value = ''
    outletId.value = await resolveOutletId()
    if (!outletId.value) return null

    const { data } = await http.get('/shifts/current', { params: { outlet_id: outletId.value } })
    shift.value = data.data
    suggestedOpeningCash.value = data.suggested_opening_cash ?? 0
    loaded.value = true
    return shift.value
  }

  async function open(openingCash) {
    const { data } = await http.post('/shifts/open', { outlet_id: outletId.value, opening_cash: openingCash })
    shift.value = data.data
    return shift.value
  }

  async function addCashMovement(type, amount, note) {
    const { data } = await http.post(`/shifts/${shift.value.id}/cash-movements`, { type, amount, note })
    shift.value = { ...shift.value, cash_movements: [...(shift.value.cash_movements ?? []), data.data] }
    return data.data
  }

  /** Error 422 dengan `difference` = ada selisih; halaman menampilkan selisih & meminta catatan. */
  async function close(actualCash, note = null) {
    const { data } = await http.post(`/shifts/${shift.value.id}/close`, { actual_cash: actualCash, note }, { silent: true })
    shift.value = data.data
    return { shift: data.data, report: data.report }
  }

  function reset() {
    shift.value = null
    loaded.value = false
    outletId.value = null
  }

  return { shift, outletId, suggestedOpeningCash, loaded, outletError, isOpen, ensureLoaded, open, addCashMovement, close, reset }
})
