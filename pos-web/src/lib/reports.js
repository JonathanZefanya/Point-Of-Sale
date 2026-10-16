import { formatNumber, formatRupiah } from '@/lib/format'

/**
 * Definisi laporan (F11, 2.11): kolom tabel + kartu ringkasan per jenis.
 * format: money | number | percent | datetime | text. Penjualan memakai tabel khususnya sendiri.
 */
const percent = (v) => `${Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`

export const REPORTS = [
  { value: 'sales', label: 'Penjualan' },
  { value: 'payments', label: 'Metode Bayar' },
  { value: 'products', label: 'Per Produk' },
  { value: 'categories', label: 'Per Kategori' },
  { value: 'cashiers', label: 'Per Kasir' },
  { value: 'profit', label: 'Laba Kotor' },
  { value: 'stock', label: 'Stok' },
  { value: 'commissions', label: 'Komisi' },
  { value: 'voids', label: 'Void & Refund' },
]

export const REPORT_DEFS = {
  payments: {
    rowKey: 'method',
    empty: 'Belum ada pembayaran pada periode ini.',
    columns: [
      { key: 'label', label: 'Metode' },
      { key: 'transactions', label: 'Transaksi', format: 'number' },
      { key: 'amount', label: 'Nominal', format: 'money' },
    ],
    summary: (s) => [{ label: 'Total diterima', value: s.total, note: 'Sudah dikurangi kembalian dan refund' }],
  },
  products: {
    rowKey: 'sku',
    empty: 'Belum ada produk terjual pada periode ini.',
    columns: [
      { key: 'name', label: 'Produk', sub: 'sku' },
      { key: 'quantity', label: 'Terjual', format: 'number' },
      { key: 'returned', label: 'Diretur', format: 'number' },
      { key: 'revenue', label: 'Penjualan bersih', format: 'money' },
      { key: 'cost', label: 'Modal', format: 'money' },
      { key: 'profit', label: 'Laba kotor', format: 'money' },
      { key: 'margin', label: 'Margin', format: 'percent' },
    ],
    summary: (s) => [
      { label: 'Penjualan bersih', value: s.revenue, note: 'Nilai produk setelah diskon, tanpa pajak & service' },
      { label: 'Unit terjual', value: s.quantity, money: false, note: s.returned ? `${formatNumber(s.returned)} unit diretur` : '' },
      { label: 'Laba kotor', value: s.profit },
    ],
  },
  categories: {
    rowKey: 'name',
    empty: 'Belum ada penjualan pada periode ini.',
    columns: [
      { key: 'name', label: 'Kategori' },
      { key: 'quantity', label: 'Terjual', format: 'number' },
      { key: 'revenue', label: 'Penjualan bersih', format: 'money' },
      { key: 'share', label: 'Porsi', format: 'percent' },
      { key: 'profit', label: 'Laba kotor', format: 'money' },
      { key: 'margin', label: 'Margin', format: 'percent' },
    ],
    summary: (s) => [
      { label: 'Penjualan bersih', value: s.revenue },
      { label: 'Laba kotor', value: s.profit },
    ],
  },
  cashiers: {
    rowKey: 'name',
    empty: 'Belum ada transaksi kasir pada periode ini.',
    columns: [
      { key: 'name', label: 'Kasir' },
      { key: 'transactions', label: 'Transaksi', format: 'number' },
      { key: 'revenue', label: 'Penjualan bersih', format: 'money' },
      { key: 'average', label: 'Rata-rata', format: 'money' },
      { key: 'manual_discount', label: 'Diskon manual', format: 'money' },
      { key: 'void_count', label: 'Void', format: 'number' },
      { key: 'refund_amount', label: 'Refund', format: 'money' },
    ],
    summary: (s) => [
      { label: 'Penjualan bersih', value: s.revenue },
      { label: 'Transaksi', value: s.transactions, money: false },
      { label: 'Diskon manual', value: s.manual_discount },
      { label: 'Void', value: s.void_amount, note: `${formatNumber(s.void_count)} transaksi` },
    ],
  },
  profit: {
    rowKey: 'date',
    empty: 'Belum ada penjualan pada periode ini.',
    columns: [
      { key: 'date', label: 'Tanggal', format: 'date' },
      { key: 'revenue', label: 'Penjualan bersih', format: 'money' },
      { key: 'cost', label: 'Modal', format: 'money' },
      { key: 'profit', label: 'Laba kotor', format: 'money' },
      { key: 'margin', label: 'Margin', format: 'percent' },
    ],
    summary: (s) => [
      { label: 'Penjualan bersih', value: s.revenue, note: 'Tanpa pajak & service charge' },
      { label: 'Modal', value: s.cost, note: 'Harga modal saat transaksi' },
      { label: 'Laba kotor', value: s.profit, note: `Margin ${percent(s.margin)}` },
    ],
  },
  stock: {
    rowKey: 'sku',
    empty: 'Belum ada produk berstok di outlet ini.',
    columns: [
      { key: 'name', label: 'Produk', sub: 'sku' },
      { key: 'quantity', label: 'Stok', format: 'number', tone: (r) => (r.status === 'out' ? 'text-danger' : r.status === 'low' ? 'text-warning' : '') },
      { key: 'qty_in', label: 'Masuk', format: 'number' },
      { key: 'qty_out', label: 'Keluar', format: 'number' },
      { key: 'cost_price', label: 'Harga modal', format: 'money' },
      { key: 'value', label: 'Nilai persediaan', format: 'money' },
    ],
    summary: (s) => [
      { label: 'Nilai persediaan', value: s.value, note: 'Stok saat ini × harga modal' },
      { label: 'Stok menipis', value: s.low, money: false },
      { label: 'Stok habis', value: s.out, money: false },
    ],
    note: 'Stok & nilai persediaan = saat ini. Kolom Masuk/Keluar mengikuti periode yang dipilih.',
  },
  commissions: {
    rowKey: 'name',
    empty: 'Belum ada penjualan karyawan pada periode ini.',
    columns: [
      { key: 'name', label: 'Karyawan' },
      { key: 'transactions', label: 'Transaksi', format: 'number' },
      { key: 'net_sales', label: 'Penjualan bersih', format: 'money' },
      { key: 'percent', label: 'Komisi', format: 'percent' },
      { key: 'commission', label: 'Nilai komisi', format: 'money' },
    ],
    summary: (s) => [
      { label: 'Total komisi', value: s.commission, note: 'Persen komisi diatur di menu Karyawan' },
      { label: 'Penjualan bersih', value: s.net_sales, note: 'Tanpa pajak & service, dikurangi retur' },
    ],
  },
  voids: {
    rowKey: (r) => `${r.type}-${r.number}-${r.at}`,
    empty: 'Tidak ada void atau refund pada periode ini.',
    columns: [
      { key: 'at', label: 'Waktu', format: 'datetime' },
      { key: 'type_label', label: 'Jenis' },
      { key: 'number', label: 'No. transaksi', mono: true },
      { key: 'amount', label: 'Nominal', format: 'money' },
      { key: 'reason', label: 'Alasan' },
      { key: 'cashier', label: 'Kasir', sub: 'approved_by', subPrefix: 'Disetujui ' },
    ],
    summary: (s) => [
      { label: 'Void', value: s.void_amount, note: `${formatNumber(s.void_count)} transaksi dibatalkan` },
      { label: 'Refund', value: s.refund_amount, note: `${formatNumber(s.refund_count)} pengembalian` },
    ],
  },
}

export function formatCell(value, format) {
  if (value === null || value === undefined || value === '') return '–'
  if (format === 'money') return formatRupiah(value)
  if (format === 'number') return formatNumber(value)
  if (format === 'percent') return percent(value)
  return value
}
