/**
 * Port 1:1 dari App\Services\Sales\SaleCalculator (backend). Frontend menghitung untuk
 * TAMPILAN; backend menghitung ulang dan menjadi sumber kebenaran (CLAUDE.md §6).
 * Semua integer rupiah; tarif dalam basis poin (10% = 1000); pembulatan half-up.
 */

/** Pembagian integer half-up untuk bilangan non-negatif. */
function divideRounded(numerator, denominator) {
  return Math.floor((numerator * 2 + denominator) / (denominator * 2))
}

function percent(amount, basisPoints) {
  return divideRounded(amount * basisPoints, 10000)
}

export function roundTo(value, to, mode) {
  if (mode === 'up') return Math.ceil(value / to) * to
  if (mode === 'down') return Math.floor(value / to) * to
  return divideRounded(value, to) * to
}

/**
 * Aturan harga dari pengaturan (GET /settings) + tarif outlet.
 * @returns {{taxBasisPoints:number, taxInclusive:boolean, serviceBasisPoints:number, roundingEnabled:boolean, roundingTo:number, roundingMode:string}}
 */
export function pricingFrom(settings, outlet) {
  return {
    taxBasisPoints: settings?.tax?.enabled ? Math.round((outlet?.tax_rate ?? 0) * 100) : 0,
    taxInclusive: !!settings?.tax?.inclusive,
    serviceBasisPoints: settings?.service_charge?.enabled ? Math.round((outlet?.service_charge_rate ?? 0) * 100) : 0,
    roundingEnabled: !!settings?.rounding?.enabled,
    roundingTo: Math.max(1, settings?.rounding?.to ?? 100),
    roundingMode: settings?.rounding?.mode ?? 'nearest',
  }
}

/**
 * @param {Array<{unit_price:number, quantity:number}>} lines
 * @returns {{subtotal:number, serviceCharge:number, taxTotal:number, rounding:number, grandTotal:number}}
 */
export function calculateTotals(lines, pricing) {
  const subtotal = lines.reduce((sum, line) => sum + line.unit_price * line.quantity, 0)
  const serviceCharge = percent(subtotal, pricing.serviceBasisPoints)
  const taxBase = subtotal + serviceCharge

  const taxTotal = pricing.taxInclusive
    ? taxBase - divideRounded(taxBase * 10000, 10000 + pricing.taxBasisPoints)
    : percent(taxBase, pricing.taxBasisPoints)

  const beforeRounding = taxBase + (pricing.taxInclusive ? 0 : taxTotal)
  const grandTotal = pricing.roundingEnabled ? roundTo(beforeRounding, pricing.roundingTo, pricing.roundingMode) : beforeRounding

  return { subtotal, serviceCharge, taxTotal, rounding: grandTotal - beforeRounding, grandTotal }
}

/**
 * Tombol nominal cepat tunai (F3.5): uang pas, pembulatan ke atas terdekat, Rp50.000, Rp100.000.
 * Hanya nominal ≥ sisa tagihan, maksimal 5 tombol.
 */
export function quickCashAmounts(due) {
  if (due <= 0) return []
  const candidates = [due, roundTo(due, 5000, 'up'), roundTo(due, 10000, 'up'), roundTo(due, 50000, 'up'), 50000, 100000, roundTo(due, 100000, 'up')]
  return [...new Set(candidates.filter((n) => n >= due))].sort((a, b) => a - b).slice(0, 5)
}
