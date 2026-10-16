import { defineStore } from 'pinia'
import { ref } from 'vue'
import { readStorage, writeStorage } from '@/lib/storage'

const OUTLET_CODE_KEY = 'pos.device.outlet_code'

/**
 * Pengaturan perangkat kasir (bukan per user): outlet tempat perangkat ini dipasang.
 * Diisi sekali saat perangkat pertama kali dipakai.
 */
export const useDeviceStore = defineStore('device', () => {
  const outletCode = ref(readStorage(OUTLET_CODE_KEY) ?? import.meta.env.VITE_DEFAULT_OUTLET_CODE ?? null)

  function setOutletCode(code) {
    outletCode.value = code ? code.trim().toUpperCase() : null
    writeStorage(OUTLET_CODE_KEY, outletCode.value)
  }

  return { outletCode, setOutletCode }
})
