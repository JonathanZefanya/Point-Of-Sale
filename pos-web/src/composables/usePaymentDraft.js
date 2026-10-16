import { computed, ref } from 'vue'

/**
 * Draft pembayaran di modal Bayar (F3.5), mengikuti aturan backend (PaymentValidator):
 * total dibayar ≥ tagihan dan non-tunai tidak boleh melebihi tagihan (kembalian hanya dari tunai).
 *
 * @param {import('vue').Ref<number>} total
 * @param {import('vue').ComputedRef<{balance:number, value:number}|null>} [points] saldo poin pelanggan (rupiah per poin)
 */
export function usePaymentDraft(total, points = computed(() => null)) {
  let nextId = 1
  const payments = ref([])
  const activeIndex = ref(0)

  const active = computed(() => payments.value[activeIndex.value])
  const paid = computed(() => payments.value.reduce((sum, p) => sum + (p.amount ?? 0), 0))
  const nonCash = computed(() => payments.value.filter((p) => p.method !== 'cash').reduce((sum, p) => sum + (p.amount ?? 0), 0))
  const remaining = computed(() => Math.max(0, total.value - paid.value))
  const change = computed(() => Math.max(0, paid.value - total.value))

  /** Sisa tagihan untuk baris aktif (tidak menghitung nominal baris itu sendiri). */
  const dueForActive = computed(() => Math.max(0, total.value - (paid.value - (active.value?.amount ?? 0))))

  const pointsPaid = computed(() => payments.value.filter((p) => p.method === 'points').reduce((s, p) => s + (p.amount ?? 0), 0))

  const problem = computed(() => {
    const pts = points.value
    if (pointsPaid.value && pts) {
      if (pointsPaid.value % pts.value) return `Nominal poin harus kelipatan Rp${pts.value.toLocaleString('id-ID')}.`
      if (pointsPaid.value > pts.balance * pts.value) return 'Poin pelanggan tidak cukup.'
    }
    if (nonCash.value > total.value) return 'Pembayaran non-tunai melebihi total. Kembalian hanya bisa dari uang tunai.'
    return ''
  })
  const canFinish = computed(() => payments.value.length > 0 && remaining.value === 0 && !problem.value)

  function reset() {
    payments.value = [{ id: nextId++, method: 'cash', amount: null, reference: '' }]
    activeIndex.value = 0
  }

  /** Non-tunai biasanya dibayar pas → isi otomatis dengan sisa tagihan. */
  function setMethod(method) {
    const row = active.value
    row.method = method
    row.intentId = null // QR dinamis hanya berlaku untuk baris QRIS dengan nominal yang sama
    if (method === 'points' && points.value) {
      // Maksimal saldo poin, dibulatkan ke bawah ke kelipatan nilai poin
      const { balance, value } = points.value
      row.amount = Math.floor(Math.min(dueForActive.value, balance * value) / value) * value || null
    } else if (method !== 'cash') row.amount = dueForActive.value || row.amount
  }

  function addMethod() {
    payments.value.push({ id: nextId++, method: 'qris', amount: remaining.value || null, reference: '' })
    activeIndex.value = payments.value.length - 1
  }

  function removeAt(index) {
    payments.value.splice(index, 1)
    if (!payments.value.length) reset()
    activeIndex.value = Math.min(activeIndex.value, payments.value.length - 1)
  }

  /** Payload untuk POST /sales: buang baris tanpa nominal. */
  const payload = computed(() =>
    payments.value
      .filter((p) => p.amount > 0)
      .map((p) => ({ method: p.method, amount: p.amount, reference: p.reference?.trim() || null, ...(p.intentId ? { intent_id: p.intentId } : {}) })),
  )

  return { payments, activeIndex, active, paid, remaining, change, dueForActive, problem, canFinish, payload, reset, setMethod, addMethod, removeAt }
}
