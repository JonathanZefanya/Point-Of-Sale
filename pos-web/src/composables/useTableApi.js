import http from '@/lib/http'

export const ORDER_TYPES = [
  { value: 'dine_in', label: 'Makan di tempat' },
  { value: 'take_away', label: 'Bawa pulang' },
  { value: 'delivery', label: 'Delivery' },
]

/** Pemanggilan API meja & bill meja (F&B 3.3). */
export function useTableApi() {
  return {
    /** Meja + bill terbuka per meja. active=true untuk denah di kasir. */
    list: (outletId, active = false) => http.get('/tables', { params: { outlet_id: outletId, active: active ? 1 : undefined } }).then((r) => r.data.data),
    save: (table) => (table.id ? http.put(`/tables/${table.id}`, table) : http.post('/tables', table)).then((r) => r.data.data),
    remove: (id) => http.delete(`/tables/${id}`),
    move: (saleId, tableId) => http.post(`/sales/held/${saleId}/move`, { table_id: tableId }).then((r) => r.data.data),
    merge: (saleId, targetSaleId) => http.post(`/sales/held/${saleId}/merge`, { target_sale_id: targetSaleId }).then((r) => r.data.data),
  }
}
