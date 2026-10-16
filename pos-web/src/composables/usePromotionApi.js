import http from '@/lib/http'

/** Pemanggilan API promo & voucher (CLAUDE.md §5: Promo). */
export function usePromotionApi() {
  return {
    list: () => http.get('/promotions').then((r) => r.data.data),
    save: (promo) => (promo.id ? http.put(`/promotions/${promo.id}`, promo) : http.post('/promotions', promo)).then((r) => r.data.data),
    remove: (id) => http.delete(`/promotions/${id}`),
  }
}
