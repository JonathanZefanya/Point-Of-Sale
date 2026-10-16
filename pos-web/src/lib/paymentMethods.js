/** Sama dengan App\Enums\PaymentMethod. Urutan = urutan tombol di modal pembayaran (F3.5). */
export const PAYMENT_METHODS = [
  { value: 'cash', label: 'Tunai' },
  { value: 'qris', label: 'QRIS' },
  { value: 'debit', label: 'Debit' },
  { value: 'credit', label: 'Kredit' },
  { value: 'ewallet', label: 'E-Wallet' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'points', label: 'Poin' }, // hanya bila ada pelanggan & loyalty aktif (2.4)
]

export const PAYMENT_LABEL = Object.fromEntries(PAYMENT_METHODS.map((m) => [m.value, m.label]))
