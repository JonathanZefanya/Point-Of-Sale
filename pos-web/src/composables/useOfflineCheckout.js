import { computed } from 'vue'
import { useCartStore } from '@/stores/cart'
import { useOfflineQueueStore } from '@/stores/offlineQueue'
import { useShiftStore } from '@/stores/shift'

/**
 * Simpan transaksi ke antrian offline (FE-3.1). Harga = harga di perangkat (keputusan pemilik);
 * server menghitung ulang pajak/total saat sinkron dan menandai perbedaan untuk ditinjau.
 * Voucher, diskon manual, poin, dan transaksi tersimpan butuh server → tidak bisa offline.
 */
export function useOfflineCheckout() {
  const cart = useCartStore()
  const queue = useOfflineQueueStore()
  const shift = useShiftStore()

  const blocker = computed(() => {
    if (cart.voucherCode || cart.manualDiscount) return 'Voucher dan diskon manual butuh koneksi ke server. Hapus dari keranjang (tombol Diskon) atau tunggu sampai online.'
    if (cart.heldSaleId) return 'Transaksi tersimpan hanya bisa diselesaikan saat online. Simpan lagi (F4) dan lanjutkan nanti.'
    return null
  })

  /** @returns objek mirip sale untuk layar sukses (tanpa nomor — nomor dibuat server saat sinkron) */
  async function save(clientId, outletId, payments) {
    const grandTotal = cart.grandTotal
    const paid = payments.reduce((sum, p) => sum + p.amount, 0)
    const display = { grand_total: grandTotal, change_amount: Math.max(0, paid - grandTotal), customer: cart.customer?.name ?? null, items: cart.itemCount }

    await queue.enqueue({
      client_id: clientId,
      outlet_id: outletId,
      shift_id: shift.shift?.id,
      completed_at: new Date().toISOString(),
      items: cart.items.map((i) => ({
        product_id: i.product_id,
        variant_id: i.variant_id,
        modifier_ids: i.modifiers.map((m) => m.id),
        quantity: i.quantity,
        unit_price: i.unit_price,
        note: i.note || null,
      })),
      payments,
      customer_id: cart.customer?.id ?? null,
      grand_total: grandTotal,
    }, display)

    return { ...display, id: null, number: null, offline: true }
  }

  return { blocker, save }
}
