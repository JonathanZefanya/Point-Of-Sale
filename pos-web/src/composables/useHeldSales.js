import { ref } from 'vue'
import { useSaleApi } from '@/composables/useSaleApi'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

/** Transaksi tersimpan (F4): simpan keranjang, daftar, lanjutkan, buang. */
export function useHeldSales(outletId) {
  const api = useSaleApi()
  const cart = useCartStore()
  const toast = useToastStore()

  const sales = ref([])
  const saving = ref(false)

  async function refresh() {
    if (!outletId.value) return
    sales.value = await api.held(outletId.value).catch(() => sales.value)
  }

  async function hold(label) {
    saving.value = true
    try {
      await api.hold({ outlet_id: outletId.value, items: cart.payloadItems, hold_label: label || null, held_sale_id: cart.heldSaleId, customer_id: cart.customer?.id ?? null, table_id: cart.table?.id ?? null, order_type: cart.effectiveOrderType })
      toast.success(label ? `Transaksi "${label}" disimpan.` : 'Transaksi disimpan.')
      cart.clear()
      await refresh()
      return true
    } catch {
      return false // pesan error sudah ditampilkan lewat toast oleh lib/http
    } finally {
      saving.value = false
    }
  }

  function resume(sale) {
    cart.loadHeld(sale)
    toast.info(`Melanjutkan ${sale.hold_label || 'transaksi tersimpan'}.`)
  }

  /** Buang transaksi tersimpan yang sedang dibuka. Undo tidak mungkin, jadi dipanggil setelah konfirmasi. */
  async function discardCurrent() {
    if (!cart.heldSaleId) return
    await api.discardHeld(cart.heldSaleId)
    toast.success('Transaksi tersimpan dibuang.')
    cart.clear()
    await refresh()
  }

  return { sales, saving, refresh, hold, resume, discardCurrent }
}
