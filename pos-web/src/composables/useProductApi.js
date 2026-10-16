import { downloadFile } from '@/lib/download'
import http from '@/lib/http'

/**
 * Mengubah data form produk menjadi FormData (mendukung upload gambar).
 * Boolean dikirim "1"/"0", null dikirim kosong (backend menganggapnya null).
 */
function toFormData(payload, data = new FormData(), prefix = '') {
  for (const [rawKey, value] of Object.entries(payload)) {
    if (value === undefined) continue
    const key = prefix ? `${prefix}[${rawKey}]` : rawKey
    if (value instanceof File) data.append(key, value)
    // Array/objek bersarang (varian, modifier) → variants[0][name]=...
    else if (Array.isArray(value) || (value && typeof value === 'object')) {
      if (Array.isArray(value) && !value.length) data.append(`${key}`, '') // kirim array kosong (hapus semua)
      else toFormData(value, data, key)
    } else if (typeof value === 'boolean') data.append(key, value ? '1' : '0')
    else data.append(key, value ?? '')
  }
  return data
}

/** Pemanggilan API produk & kategori (CLAUDE.md §5: Kategori, Produk). */
export function useProductApi() {
  return {
    list: (params) => http.get('/products', { params }).then((r) => r.data),
    get: (id) => http.get(`/products/${id}`).then((r) => r.data.data),
    create: (payload) => http.post('/products', toFormData(payload)).then((r) => r.data.data),
    // PHP tidak membaca file pada PUT → kirim POST + _method=PUT
    update: (id, payload) => http.post(`/products/${id}`, toFormData({ ...payload, _method: 'PUT' })).then((r) => r.data.data),
    remove: (id) => http.delete(`/products/${id}`),

    // Import/export Excel (2.10)
    exportExcel: (params) => downloadFile('/products/export', params, 'produk.xlsx'),
    downloadTemplate: () => downloadFile('/products/import/template', {}, 'template-import-produk.xlsx'),
    /** preview=true hanya memeriksa (error per baris); false menyimpan semua. silent: error ditampilkan di modal. */
    importFile: (file, outletId, preview) => {
      const data = new FormData()
      data.append('file', file)
      data.append('preview', preview ? '1' : '0')
      if (outletId) data.append('outlet_id', outletId)
      return http.post('/products/import', data, { silent: true }).then((r) => r.data.data)
    },

    categories: () => http.get('/categories').then((r) => r.data.data),
    createCategory: (name) => http.post('/categories', { name }).then((r) => r.data.data),

    modifierGroups: () => http.get('/modifier-groups').then((r) => r.data.data),
    saveModifierGroup: (group) =>
      (group.id ? http.put(`/modifier-groups/${group.id}`, group) : http.post('/modifier-groups', group)).then((r) => r.data.data),
    removeModifierGroup: (id) => http.delete(`/modifier-groups/${id}`),
  }
}
