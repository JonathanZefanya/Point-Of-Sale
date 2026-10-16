import Dexie from 'dexie'

/**
 * Penyimpanan lokal perangkat kasir (FE-3.1):
 * - cache: data terakhir dari server (katalog per outlet, pengaturan, outlet) agar kasir tetap bisa jualan saat API tidak terjangkau
 * - queue: transaksi offline yang menunggu dikirim ke POST /sales/sync (client_id = idempotency key)
 */
export const db = new Dexie('pos-offline')

db.version(1).stores({
  cache: '&key',
  queue: '&client_id, status, user_id, shift_id, created_at',
})

/** Simpan ke cache; gagal (mis. mode privat browser) tidak boleh mengganggu kasir. */
export async function cachePut(key, value) {
  try {
    await db.cache.put({ key, value, saved_at: new Date().toISOString() })
  } catch {
    // abaikan
  }
}

export async function cacheGet(key) {
  try {
    return (await db.cache.get(key)) ?? null
  } catch {
    return null
  }
}

/** Error karena server tidak terjangkau (bukan error validasi/bisnis dari server). */
export const isNetworkError = (e) => !e?.response && e?.status === 0
