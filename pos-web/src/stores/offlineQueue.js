import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import http from '@/lib/http'
import { db } from '@/lib/offlineDb'
import { useAuthStore } from '@/stores/auth'

const BATCH = 50
const INTERVAL_MS = 30000

/**
 * Antrian transaksi offline (FE-3.1). Disimpan di IndexedDB sehingga aman bila halaman dimuat ulang.
 * Sinkron otomatis saat koneksi kembali, tiap 30 detik, dan setelah transaksi baru masuk antrian.
 * Hanya transaksi milik kasir yang sedang login yang dikirim (server mengikat transaksi ke shift kasir).
 */
export const useOfflineQueueStore = defineStore('offlineQueue', () => {
  const items = ref([]) // isi antrian milik kasir ini
  const syncing = ref(false)
  const lastSyncAt = ref(null)
  let timer = null

  const userId = () => useAuthStore().user?.id ?? null
  const pending = computed(() => items.value.filter((i) => i.status === 'pending'))
  const failed = computed(() => items.value.filter((i) => i.status === 'failed'))
  const pendingForShift = (shiftId) => items.value.filter((i) => i.shift_id === shiftId && i.status !== 'synced').length

  async function refresh() {
    try {
      items.value = userId() ? await db.queue.where('user_id').equals(userId()).sortBy('created_at') : []
    } catch {
      items.value = []
    }
  }

  /** Simpan transaksi offline. payload sesuai aturan POST /sales/sync. */
  async function enqueue(payload, display) {
    await db.queue.put({ ...payload, user_id: userId(), status: 'pending', message: null, display, created_at: new Date().toISOString() })
    await refresh()
    sync()
  }

  async function sync() {
    if (syncing.value || !navigator.onLine) return
    await refresh()
    const batch = pending.value.slice(0, BATCH)
    if (!batch.length) return
    syncing.value = true
    try {
      const sales = batch.map(({ status: _s, message: _m, display: _d, user_id: _u, created_at: _c, ...sale }) => sale)
      const { data } = await http.post('/sales/sync', { sales }, { silent: true, timeout: 60000 })
      for (const result of data.data) {
        if (result.status === 'created' || result.status === 'duplicate') {
          await db.queue.delete(result.client_id)
        } else {
          await db.queue.update(result.client_id, { status: 'failed', message: result.message })
        }
      }
      lastSyncAt.value = new Date()
    } catch {
      // Server belum terjangkau: coba lagi pada jadwal berikutnya
    } finally {
      syncing.value = false
      await refresh()
    }
    if (pending.value.length && navigator.onLine) sync() // sisa antrian > satu batch
  }

  /** Kirim ulang transaksi yang gagal (mis. setelah masalahnya diperbaiki di server). */
  async function retry(clientId) {
    await db.queue.update(clientId, { status: 'pending', message: null })
    await sync()
  }

  /** Buang dari antrian — transaksi tidak akan tercatat. Dipanggil setelah konfirmasi. */
  async function discard(clientId) {
    await db.queue.delete(clientId)
    await refresh()
  }

  function start() {
    refresh().then(sync)
    window.addEventListener('online', sync)
    clearInterval(timer)
    timer = setInterval(sync, INTERVAL_MS)
  }

  function stop() {
    window.removeEventListener('online', sync)
    clearInterval(timer)
  }

  return { items, pending, failed, syncing, lastSyncAt, pendingForShift, refresh, enqueue, sync, retry, discard, start, stop }
})
