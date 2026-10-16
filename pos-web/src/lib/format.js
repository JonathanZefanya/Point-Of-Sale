const rupiahFormatter = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
})

const numberFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 })

const dateFormatter = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
const timeFormatter = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' })
const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

/**
 * Uang disimpan sebagai integer rupiah. 15500 → "Rp15.500" (tanpa spasi, tanpa ",00").
 * Nilai negatif → "-Rp15.500".
 */
export function formatRupiah(value) {
  const amount = Math.round(Number(value) || 0)
  return rupiahFormatter.format(amount).replace(/\s/g, '')
}

/** Angka tanpa simbol mata uang: 15500 → "15.500". Dipakai di kolom tabel/keranjang. */
export function formatNumber(value) {
  return numberFormatter.format(Number(value) || 0)
}

/** "15500", "15.500", "Rp 15.500" → 15500. Mengembalikan null jika kosong. */
export function parseRupiah(input) {
  if (input === null || input === undefined) return null
  const digits = String(input).replace(/[^\d-]/g, '')
  if (digits === '' || digits === '-') return null
  return parseInt(digits, 10)
}

function toDate(value) {
  if (!value) return null
  const date = value instanceof Date ? value : new Date(value)
  return Number.isNaN(date.getTime()) ? null : date
}

/** "15 Okt 2026" */
export function formatDate(value) {
  const date = toDate(value)
  return date ? dateFormatter.format(date) : '-'
}

/** "08.30" */
export function formatTime(value) {
  const date = toDate(value)
  return date ? timeFormatter.format(date) : '-'
}

/** "15 Okt 2026, 08.30" */
export function formatDateTime(value) {
  const date = toDate(value)
  return date ? dateTimeFormatter.format(date) : '-'
}

/** Tanggal lokal untuk query string API: "2026-10-15" */
export function toIsoDate(value = new Date()) {
  const date = toDate(value) ?? new Date()
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}
