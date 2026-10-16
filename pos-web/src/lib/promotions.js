import { formatDate, formatRupiah } from '@/lib/format'

export const PROMOTION_TYPES = [
  { value: 'item_percent', label: 'Diskon produk (%)', hint: 'Potongan persen untuk produk/kategori tertentu atau semua produk.' },
  { value: 'item_amount', label: 'Diskon produk (Rp)', hint: 'Potongan rupiah per unit produk.' },
  { value: 'order_percent', label: 'Diskon transaksi (%)', hint: 'Potongan persen dari total belanja, bisa dengan minimum belanja.' },
  { value: 'order_amount', label: 'Diskon transaksi (Rp)', hint: 'Potongan rupiah dari total belanja, bisa dengan minimum belanja.' },
  { value: 'buy_x_get_y', label: 'Beli X gratis Y', hint: 'Setiap membeli sejumlah produk, pelanggan mendapat produk gratis.' },
]

export const WEEKDAYS = [
  { value: 1, label: 'Sen' },
  { value: 2, label: 'Sel' },
  { value: 3, label: 'Rab' },
  { value: 4, label: 'Kam' },
  { value: 5, label: 'Jum' },
  { value: 6, label: 'Sab' },
  { value: 7, label: 'Min' },
]

export const isPercentType = (type) => type === 'item_percent' || type === 'order_percent'
export const isItemType = (type) => type === 'item_percent' || type === 'item_amount'
export const isOrderType = (type) => type === 'order_percent' || type === 'order_amount'

/** Ringkasan nilai promo untuk tabel, mis. "10% · min. belanja Rp50.000". */
export function describeValue(promo) {
  const r = promo.rules ?? {}
  if (promo.type === 'buy_x_get_y') return `Beli ${r.buy_quantity} gratis ${r.get_quantity}`
  const parts = [isPercentType(promo.type) ? `${promo.value}%` : formatRupiah(promo.value)]
  if (promo.type === 'item_amount') parts[0] += ' per unit'
  if (r.min_purchase) parts.push(`min. belanja ${formatRupiah(r.min_purchase)}`)
  if (r.max_discount) parts.push(`maks. ${formatRupiah(r.max_discount)}`)
  return parts.join(' · ')
}

/** Ringkasan waktu berlaku, mis. "1 Okt – 31 Okt · Sen–Jum 15:00–17:00". */
export function describeSchedule(promo) {
  const r = promo.rules ?? {}
  const parts = []
  if (promo.starts_at || promo.ends_at) {
    parts.push(`${promo.starts_at ? formatDate(promo.starts_at) : 'Sekarang'} – ${promo.ends_at ? formatDate(promo.ends_at) : 'seterusnya'}`)
  }
  if (r.days?.length && r.days.length < 7) parts.push(WEEKDAYS.filter((d) => r.days.includes(d.value)).map((d) => d.label).join(', '))
  if (r.start_time && r.end_time) parts.push(`${r.start_time}–${r.end_time}`)
  return parts.join(' · ') || 'Selalu'
}
