import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import http from '@/lib/http'
import { cacheGet, cachePut, isNetworkError } from '@/lib/offlineDb'
import { readStorage, writeStorage } from '@/lib/storage'

const CURRENT_KEY = 'pos.backoffice.outlet_id'

/**
 * Outlet yang boleh diakses user + outlet yang sedang dipilih di back office
 * (dipakai halaman stok, laporan, dll.). Default = outlet pertama (CLAUDE.md §7.1 no. 3).
 */
export const useOutletStore = defineStore('outlet', () => {
  const outlets = ref([])
  const loaded = ref(false)
  const selectedId = ref(Number(readStorage(CURRENT_KEY)) || null)
  let pending = null

  const current = computed(() => outlets.value.find((o) => o.id === selectedId.value) ?? outlets.value[0] ?? null)
  const hasMany = computed(() => outlets.value.length > 1)

  function ensureLoaded() {
    if (loaded.value) return Promise.resolve(outlets.value)
    pending ??= http
      .get('/outlets', { params: { per_page: 100 } })
      .then(({ data }) => {
        outlets.value = data.data
        loaded.value = true
        cachePut('outlets', data.data)
        return outlets.value
      })
      // Offline: daftar outlet terakhir di perangkat (tarif pajak untuk total kasir) — FE-3.1
      .catch(async (e) => {
        const cached = isNetworkError(e) ? await cacheGet('outlets') : null
        if (!cached) throw e
        outlets.value = cached.value
        return outlets.value
      })
      .finally(() => (pending = null))
    return pending
  }

  function select(id) {
    selectedId.value = Number(id)
    writeStorage(CURRENT_KEY, String(selectedId.value))
  }

  /** Dipanggil saat logout agar user berikutnya tidak melihat daftar outlet user sebelumnya. */
  function reset() {
    outlets.value = []
    loaded.value = false
  }

  return { outlets, loaded, current, hasMany, ensureLoaded, select, reset }
})
