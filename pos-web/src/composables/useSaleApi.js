import http from '@/lib/http'

/** Pemanggilan API penjualan (CLAUDE.md §5: Penjualan). */
export function useSaleApi() {
  return {
    /** silent: error stok (409) & pembayaran (422) ditangani di modal pembayaran. */
    checkout: (payload, idempotencyKey) =>
      http.post('/sales', payload, { headers: { 'Idempotency-Key': idempotencyKey }, silent: true }).then((r) => r.data.data),
    hold: (payload) => http.post('/sales/hold', payload).then((r) => r.data.data),
    held: (outletId) => http.get('/sales/held', { params: { outlet_id: outletId } }).then((r) => r.data.data),
    discardHeld: (id) => http.delete(`/sales/held/${id}`),
    list: (params) => http.get('/sales', { params }).then((r) => r.data),
    get: (id) => http.get(`/sales/${id}`).then((r) => r.data.data),
    void: (id, reason, supervisorPin) =>
      http.post(`/sales/${id}/void`, { reason, supervisor_pin: supervisorPin }, { silent: true }).then((r) => r.data.data),
  }
}

/** Kunci idempotensi: satu per sesi pembayaran, dipakai ulang saat kirim ulang (cegah transaksi dobel). */
export function newIdempotencyKey() {
  return globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`
}
