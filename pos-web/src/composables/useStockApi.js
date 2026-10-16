import http from '@/lib/http'

/** Pemanggilan API stok (CLAUDE.md §5: Stok). */
export function useStockApi() {
  return {
    /** → { data, meta, summary: { low, out, outlet_id } } */
    list: (params) => http.get('/stocks', { params }).then((r) => r.data),
    /** → { data, meta, product: { id, name, sku, unit, outlet_id, quantity } } */
    movements: (productId, params) => http.get(`/stocks/${productId}/movements`, { params }).then((r) => r.data),
    adjust: (payload) => http.post('/stock-adjustments', payload).then((r) => r.data.data),

    // Stock opname (F8, 2.7)
    opnames: (params) => http.get('/stock-opnames', { params }).then((r) => r.data),
    opname: (id) => http.get(`/stock-opnames/${id}`).then((r) => r.data.data),
    /** silent: 409 (masih ada draf) ditangani pemanggil. */
    startOpname: (payload) => http.post('/stock-opnames', payload, { silent: true }).then((r) => r.data.data),
    saveOpname: (id, items, note) => http.put(`/stock-opnames/${id}`, { items, note }).then((r) => r.data.data),
    applyOpname: (id) => http.post(`/stock-opnames/${id}/apply`).then((r) => r.data.data),
    cancelOpname: (id) => http.post(`/stock-opnames/${id}/cancel`).then((r) => r.data.data),

    // Transfer stok (F10, 2.9)
    transfers: (params) => http.get('/stock-transfers', { params }).then((r) => r.data),
    transfer: (id) => http.get(`/stock-transfers/${id}`).then((r) => r.data.data),
    /** silent: stok kurang (409) & error per baris ditampilkan di form. */
    createTransfer: (payload) => http.post('/stock-transfers', payload, { silent: true }).then((r) => r.data.data),
    receiveTransfer: (id, items, note) => http.post(`/stock-transfers/${id}/receive`, { items, note }, { silent: true }).then((r) => r.data.data),
    cancelTransfer: (id) => http.post(`/stock-transfers/${id}/cancel`).then((r) => r.data.data),
    /** Semua outlet (tujuan transfer), termasuk yang tidak ditugaskan ke user. */
    allOutlets: () => http.get('/outlets', { params: { all: 1, per_page: 100 } }).then((r) => r.data.data),
  }
}

/** Pilihan alasan penyesuaian — sama dengan App\Enums\AdjustmentReason. */
export const ADJUSTMENT_REASONS = [
  { value: 'damaged', label: 'Rusak' },
  { value: 'lost', label: 'Hilang' },
  { value: 'expired', label: 'Kadaluarsa' },
  { value: 'other', label: 'Lainnya' },
]
