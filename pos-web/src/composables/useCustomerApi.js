import http from '@/lib/http'

/** Pemanggilan API pelanggan (CLAUDE.md §5: Pelanggan). */
export function useCustomerApi() {
  return {
    list: (params) => http.get('/customers', { params }).then((r) => r.data),
    get: (id) => http.get(`/customers/${id}`).then((r) => r.data.data),
    save: (customer) =>
      (customer.id ? http.put(`/customers/${customer.id}`, customer) : http.post('/customers', customer)).then((r) => r.data.data),
    remove: (id) => http.delete(`/customers/${id}`),
    sales: (id, params) => http.get(`/customers/${id}/sales`, { params }).then((r) => r.data),
  }
}

export const CUSTOMER_GROUPS = [
  { value: 'member', label: 'Member' },
  { value: 'reseller', label: 'Reseller' },
  { value: 'vip', label: 'VIP' },
]
