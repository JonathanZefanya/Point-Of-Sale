import http from '@/lib/http'

/** Pemanggilan API manajemen karyawan (CLAUDE.md §5: User & Role, Outlet). */
export function useUserApi() {
  return {
    list: (params) => http.get('/users', { params }).then((r) => r.data),
    get: (id) => http.get(`/users/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/users', payload).then((r) => r.data.data),
    update: (id, payload) => http.put(`/users/${id}`, payload).then((r) => r.data.data),
    remove: (id) => http.delete(`/users/${id}`),
    roles: () => http.get('/roles').then((r) => r.data.data),
    outlets: () => http.get('/outlets', { params: { per_page: 100 } }).then((r) => r.data.data),
  }
}
