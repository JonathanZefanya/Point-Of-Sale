import { onBeforeUnmount, ref, watch } from 'vue'
import http from '@/lib/http'
import { isNetworkError } from '@/lib/offlineDb'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

/**
 * Minta total versi server (POST /sales/quote) setiap keranjang/diskon berubah, dengan debounce.
 * Promo, voucher, dan diskon manual hanya dihitung di backend (2.5) — frontend tidak menduplikasi aturannya.
 */
export function useCartQuote(outletId, delay = 250) {
  const cart = useCartStore()
  const toast = useToastStore()
  const loading = ref(false)
  let timer = null

  async function fetchQuote() {
    clearTimeout(timer)
    timer = null
    if (cart.isEmpty || !outletId.value) return cart.setQuote(null)
    const key = cart.quoteKey
    loading.value = true
    try {
      const { data } = await http.post(
        '/sales/quote',
        { outlet_id: outletId.value, items: cart.payloadItems, voucher_code: cart.voucherCode, manual_discount: cart.manualDiscount },
        { silent: true },
      )
      // Abaikan jawaban lama bila keranjang sudah berubah lagi
      if (key === cart.quoteKey) cart.setQuote(data.data, key)
    } catch (e) {
      // Offline: total dihitung lokal dari harga perangkat (FE-3.1)
      if (isNetworkError(e)) cart.setQuote(null)
      if (e.fieldErrors?.voucher_code) {
        // Voucher tidak berlaku lagi (kuota habis/di luar jam): lepas dan beri tahu kasir
        toast.error(e.fieldErrors.voucher_code)
        cart.setVoucher(null)
      }
    } finally {
      loading.value = false
    }
  }

  /** Langsung hitung (mis. sebelum membuka modal pembayaran) bila quote belum segar. */
  function flush() {
    if (cart.quoteFresh || cart.isEmpty) return Promise.resolve()
    return fetchQuote()
  }

  watch(
    () => [cart.quoteKey, outletId.value],
    () => {
      clearTimeout(timer)
      timer = setTimeout(fetchQuote, delay)
    },
  )

  onBeforeUnmount(() => clearTimeout(timer))

  return { loading, flush }
}
