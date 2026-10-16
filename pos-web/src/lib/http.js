import axios from 'axios'

/**
 * Satu-satunya instance HTTP. Menangani:
 * - token Bearer dari store auth
 * - 401 → logout + ke halaman login
 * - 422 → error.fieldErrors ({ price: 'Harga wajib diisi.' }) untuk ditampilkan di field
 * - error lain → toast dengan pesan dari backend
 *
 * Handler di-inject dari main.js lewat configureHttp() agar file ini tidak
 * bergantung langsung pada store/router (menghindari import melingkar).
 */
const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  timeout: 20000,
  headers: { Accept: 'application/json' },
})

const handlers = {
  getToken: () => null,
  onUnauthorized: () => {},
  notifyError: () => {},
}

export function configureHttp(options) {
  Object.assign(handlers, options)
}

http.interceptors.request.use((config) => {
  const token = handlers.getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    const data = error.response?.data
    const silent = error.config?.silent === true

    error.status = status ?? 0
    error.userMessage = messageFor(error, status, data)

    if (status === 422) {
      // Ambil pesan pertama per field; komponen form menampilkannya di bawah input
      error.fieldErrors = Object.fromEntries(
        Object.entries(data?.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
      )
    } else if (status === 401 && !error.config?.skipAuthRedirect) {
      handlers.onUnauthorized()
    } else if (!silent) {
      handlers.notifyError(error.userMessage)
    }

    return Promise.reject(error)
  },
)

function messageFor(error, status, data) {
  if (data?.message) return data.message
  if (error.code === 'ECONNABORTED') return 'Server terlalu lama merespons. Periksa koneksi lalu coba lagi.'
  if (!error.response) return 'Tidak dapat terhubung ke server. Periksa koneksi internet lalu coba lagi.'
  if (status >= 500) return 'Terjadi kesalahan di server. Coba lagi; jika tetap gagal, hubungi admin.'
  return 'Permintaan gagal diproses. Coba lagi.'
}

export default http
