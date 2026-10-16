import http from '@/lib/http'

/** Unduh file lewat http (agar token terkirim), lalu simpan dengan nama dari server. */
export async function downloadFile(url, params = {}, fallbackName = 'unduhan.xlsx') {
  const response = await http.get(url, { params, responseType: 'blob' })
  const name = /filename="?([^";]+)"?/.exec(response.headers['content-disposition'] ?? '')?.[1] ?? fallbackName
  const objectUrl = URL.createObjectURL(response.data)
  const link = Object.assign(document.createElement('a'), { href: objectUrl, download: name })
  link.click()
  URL.revokeObjectURL(objectUrl)
}
