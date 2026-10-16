import http from '@/lib/http'

export const PO_STATUSES = [
  { value: 'draft', label: 'Draf', tone: 'neutral' },
  { value: 'ordered', label: 'Dipesan', tone: 'warning' },
  { value: 'partial', label: 'Diterima sebagian', tone: 'warning' },
  { value: 'received', label: 'Diterima', tone: 'success' },
  { value: 'cancelled', label: 'Dibatalkan', tone: 'neutral' },
]

/** Pemanggilan API supplier & pembelian (CLAUDE.md §5: Supplier, Pembelian). */
export function usePurchaseApi() {
  return {
    suppliers: (params) => http.get('/suppliers', { params }).then((r) => r.data),
    saveSupplier: (s) => (s.id ? http.put(`/suppliers/${s.id}`, s) : http.post('/suppliers', s)).then((r) => r.data.data),
    removeSupplier: (id) => http.delete(`/suppliers/${id}`),

    orders: (params) => http.get('/purchase-orders', { params }).then((r) => r.data),
    order: (id) => http.get(`/purchase-orders/${id}`).then((r) => r.data.data),
    saveOrder: (po) => (po.id ? http.put(`/purchase-orders/${po.id}`, po) : http.post('/purchase-orders', po)).then((r) => r.data.data),
    /** silent: error jumlah (422) ditampilkan per baris di modal terima barang. */
    receive: (id, items, note) => http.post(`/purchase-orders/${id}/receive`, { items, note }, { silent: true }).then((r) => r.data.data),
    cancel: (id) => http.post(`/purchase-orders/${id}/cancel`).then((r) => r.data.data),
  }
}
