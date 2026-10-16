import { toIsoDate } from '@/lib/format'

/** Preset periode laporan (F11.2). Semua tanggal lokal (YYYY-MM-DD). */
export const PERIOD_PRESETS = [
  { value: 'today', label: 'Hari ini' },
  { value: 'yesterday', label: 'Kemarin' },
  { value: '7days', label: '7 hari' },
  { value: 'this_month', label: 'Bulan ini' },
  { value: 'last_month', label: 'Bulan lalu' },
  { value: 'custom', label: 'Kustom' },
]

/** @returns {{from: string, to: string}} */
export function rangeFor(preset, now = new Date()) {
  const day = (offset) => toIsoDate(new Date(now.getFullYear(), now.getMonth(), now.getDate() + offset))
  switch (preset) {
    case 'yesterday':
      return { from: day(-1), to: day(-1) }
    case '7days':
      return { from: day(-6), to: day(0) }
    case 'this_month':
      return { from: toIsoDate(new Date(now.getFullYear(), now.getMonth(), 1)), to: day(0) }
    case 'last_month':
      return {
        from: toIsoDate(new Date(now.getFullYear(), now.getMonth() - 1, 1)),
        to: toIsoDate(new Date(now.getFullYear(), now.getMonth(), 0)),
      }
    default:
      return { from: day(0), to: day(0) }
  }
}
