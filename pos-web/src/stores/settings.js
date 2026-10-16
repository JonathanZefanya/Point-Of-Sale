import { defineStore } from 'pinia'
import { ref } from 'vue'
import http from '@/lib/http'
import { cacheGet, cachePut, isNetworkError } from '@/lib/offlineDb'

/** Pengaturan aplikasi (GET /settings): pajak, service charge, pembulatan, struk. */
export const useSettingsStore = defineStore('settings', () => {
  const settings = ref(null)
  let pending = null

  function ensureLoaded() {
    if (settings.value) return Promise.resolve(settings.value)
    pending ??= http
      .get('/settings')
      .then(({ data }) => {
        cachePut('settings', data.data)
        return (settings.value = data.data)
      })
      // Offline: pengaturan terakhir di perangkat dipakai untuk menghitung total (FE-3.1)
      .catch(async (e) => {
        const cached = isNetworkError(e) ? await cacheGet('settings') : null
        if (!cached) throw e
        return (settings.value = cached.value)
      })
      .finally(() => (pending = null))
    return pending
  }

  /** Muat ulang setelah pengaturan diubah. */
  function refresh() {
    settings.value = null
    return ensureLoaded()
  }

  return { settings, ensureLoaded, refresh }
})
