import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { calculateTotals, pricingFrom } from '@/lib/pricing'
import { useOutletStore } from '@/stores/outlet'
import { useSettingsStore } from '@/stores/settings'
import { useShiftStore } from '@/stores/shift'

/**
 * SATU-SATUNYA tempat state keranjang (CLAUDE.md §6). Perhitungan total ada di getter,
 * bukan di template. Total ini untuk tampilan — backend menghitung ulang saat bayar.
 */
export const useCartStore = defineStore('cart', () => {
  const items = ref([]) // { key, product_id, name, sku, unit, unit_price, quantity, note }
  const heldSaleId = ref(null) // transaksi tersimpan yang sedang dilanjutkan (F4)
  const holdLabel = ref('')
  const table = ref(null) // { id, name } — bill meja (F&B 3.3)
  const orderType = ref('dine_in') // dine_in | take_away | delivery
  const fnbMode = ref(false) // outlet punya meja; selain itu tipe pesanan tidak dikirim (toko retail)
  const effectiveOrderType = computed(() => (fnbMode.value ? orderType.value : null))
  const customer = ref(null) // { id, name, phone, points } — null = Umum
  const stockIssues = ref({}) // "product_id:variant_id" → sisa stok, dari respons 409 (F3.9)
  const voucherCode = ref(null) // kode voucher terpasang (2.5)
  const manualDiscount = ref(null) // { type: 'percent'|'amount', value } — null = tanpa diskon manual
  const quote = ref(null) // hasil POST /sales/quote + key keranjang saat diminta
  let nextKey = 1

  const pricing = computed(() => {
    const outlet = useOutletStore().outlets.find((o) => o.id === useShiftStore().outletId)
    return pricingFrom(useSettingsStore().settings, outlet)
  })

  /** Kunci isi keranjang + diskon; quote server hanya "segar" bila kuncinya sama. */
  const quoteKey = computed(() => JSON.stringify([payloadItems.value, voucherCode.value, manualDiscount.value]))
  const quoteFresh = computed(() => !!quote.value && quote.value.key === quoteKey.value)

  // Total server (promo/voucher/diskon) dipakai bila ada; hitungan lokal hanya sebelum quote pertama datang
  const localTotals = computed(() => calculateTotals(items.value, pricing.value))
  const totals = computed(() => {
    const q = quote.value
    if (!q || isEmpty.value) return { ...localTotals.value, discountTotal: 0 }
    return { subtotal: q.subtotal, discountTotal: q.discount_total, serviceCharge: q.service_charge, taxTotal: q.tax_total, rounding: q.rounding, grandTotal: q.grand_total }
  })
  const subtotal = computed(() => totals.value.subtotal)
  const discountTotal = computed(() => totals.value.discountTotal)
  const serviceCharge = computed(() => totals.value.serviceCharge)
  const taxTotal = computed(() => totals.value.taxTotal)
  const rounding = computed(() => totals.value.rounding)
  const grandTotal = computed(() => totals.value.grandTotal)
  const promotions = computed(() => (quote.value && !isEmpty.value ? quote.value.promotions : []))
  const manualDiscountAmount = computed(() => (quote.value && !isEmpty.value ? quote.value.manual_discount : 0))
  const needsSupervisor = computed(() => !!quote.value?.needs_supervisor)
  /** Diskon per baris (urutan sama dengan items) — hanya dari quote yang segar. */
  const lineDiscounts = computed(() => (quoteFresh.value ? quote.value.lines.map((l) => l.discount) : []))

  function setQuote(data, key) {
    quote.value = data ? { ...data, key } : null
  }

  function setVoucher(code) {
    voucherCode.value = code || null
  }

  function setManualDiscount(value) {
    manualDiscount.value = value && value.value > 0 ? value : null
  }
  const itemCount = computed(() => items.value.reduce((sum, i) => sum + i.quantity, 0))
  const isEmpty = computed(() => items.value.length === 0)
  const taxPercentLabel = computed(() => `${pricing.value.taxBasisPoints / 100}%`)

  /**
   * Produk sama (varian & modifier sama) tanpa catatan → jumlah bertambah (F3.1).
   * options: { variant, modifiers: [{id, name, price}] } untuk produk bervarian/bermodifier (2.1).
   */
  function add(product, quantity = 1, options = {}) {
    const variant = options.variant ?? null
    const modifiers = options.modifiers ?? []
    const signature = `${variant?.id ?? 0}|${modifiers.map((m) => m.id).sort().join(',')}`
    const existing = items.value.find((i) => i.product_id === product.id && i.signature === signature && !i.note)
    if (existing) {
      existing.quantity += quantity
      return existing
    }
    const item = {
      key: nextKey++,
      product_id: product.id,
      variant_id: variant?.id ?? null,
      modifiers,
      signature,
      name: variant ? `${product.name} - ${variant.name}` : product.name,
      sku: variant?.sku ?? product.sku,
      unit: product.unit,
      unit_price: (variant?.sell_price ?? product.sell_price) + modifiers.reduce((sum, m) => sum + m.price, 0),
      quantity,
      note: '',
    }
    items.value.push(item)
    return item
  }

  function setQuantity(key, quantity) {
    const item = items.value.find((i) => i.key === key)
    if (!item) return
    const value = Math.floor(Number(quantity))
    if (Number.isFinite(value) && value >= 1) item.quantity = Math.min(value, 100000)
  }

  function increment(key) {
    const item = items.value.find((i) => i.key === key)
    if (item) item.quantity += 1
  }

  function decrement(key) {
    const item = items.value.find((i) => i.key === key)
    if (item && item.quantity > 1) item.quantity -= 1
  }

  /** Mengembalikan fungsi undo untuk toast "Batalkan" (CLAUDE.md §7.1 no. 8). */
  function remove(key) {
    const index = items.value.findIndex((i) => i.key === key)
    if (index === -1) return () => {}
    const [removed] = items.value.splice(index, 1)
    return () => items.value.splice(Math.min(index, items.value.length), 0, removed)
  }

  function setNote(key, note) {
    const item = items.value.find((i) => i.key === key)
    if (item) item.note = note.slice(0, 200)
  }

  /** Muat transaksi tersimpan ke keranjang. Harga diambil ulang dari produk saat bayar. */
  function loadHeld(sale) {
    items.value = sale.items.map((i) => ({
      key: nextKey++,
      product_id: i.product_id,
      variant_id: i.variant_id ?? null,
      modifiers: i.modifiers ?? [],
      signature: `${i.variant_id ?? 0}|${(i.modifiers ?? []).map((m) => m.id).sort().join(',')}`,
      name: i.product_name,
      sku: i.sku,
      unit: '',
      unit_price: i.unit_price,
      quantity: i.quantity,
      note: i.note ?? '',
    }))
    heldSaleId.value = sale.id
    table.value = sale.table ?? null
    orderType.value = sale.order_type ?? 'dine_in'
    holdLabel.value = sale.hold_label ?? ''
    customer.value = sale.customer ?? null
    stockIssues.value = {}
  }

  function setStockIssues(issues) {
    stockIssues.value = Object.fromEntries(issues.map((i) => [`${i.product_id}:${i.variant_id ?? ''}`, i.available]))
  }

  function setCustomer(value) {
    customer.value = value
  }

  function clear() {
    items.value = []
    customer.value = null
    heldSaleId.value = null
    holdLabel.value = ''
    table.value = null
    orderType.value = 'dine_in'
    stockIssues.value = {}
    voucherCode.value = null
    manualDiscount.value = null
    quote.value = null
  }

  /** Payload items untuk POST /sales dan /sales/hold. Harga tidak dikirim — server yang menentukan. */
  const payloadItems = computed(() =>
    items.value.map((i) => ({
      product_id: i.product_id,
      variant_id: i.variant_id,
      modifier_ids: i.modifiers.map((m) => m.id),
      quantity: i.quantity,
      note: i.note || null,
    })),
  )

  return {
    items,
    heldSaleId,
    holdLabel,
    table,
    orderType,
    fnbMode,
    effectiveOrderType,
    customer,
    stockIssues,
    voucherCode,
    manualDiscount,
    quote,
    quoteKey,
    quoteFresh,
    subtotal,
    discountTotal,
    promotions,
    manualDiscountAmount,
    needsSupervisor,
    lineDiscounts,
    serviceCharge,
    taxTotal,
    rounding,
    grandTotal,
    itemCount,
    isEmpty,
    taxPercentLabel,
    pricing,
    payloadItems,
    add,
    setQuantity,
    increment,
    decrement,
    remove,
    setNote,
    loadHeld,
    setStockIssues,
    setCustomer,
    setTable: (value) => {
      table.value = value
      if (value) orderType.value = 'dine_in'
    },
    setOrderType: (value) => (orderType.value = value),
    setQuote,
    setVoucher,
    setManualDiscount,
    clear,
  }
})
