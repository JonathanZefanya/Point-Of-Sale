/** Pemetaan form produk ↔ API (dipakai ProductFormView). */

export function blankForm(keep = {}) {
  return {
    name: '',
    category_id: null,
    sell_price: null,
    cost_price: null,
    sku: '',
    barcode: '',
    type: 'goods',
    unit: 'pcs',
    track_stock: true,
    min_stock: 0,
    is_active: true,
    image: null,
    remove_image: false,
    initial_stock: '',
    outlet_id: null,
    variants: [],
    modifier_group_ids: [],
    outlet_prices: {}, // outlet_id → { sell_price, is_available } (2.8)
    ...keep,
  }
}

/** Respons GET /products/{id} → isi form ubah. */
export function fromProduct(product) {
  return blankForm({
    name: product.name,
    category_id: product.category_id,
    sell_price: product.sell_price,
    cost_price: product.cost_price ?? null,
    sku: product.sku,
    barcode: product.barcode ?? '',
    type: product.type,
    unit: product.unit,
    track_stock: product.track_stock,
    min_stock: product.min_stock,
    is_active: product.is_active,
    variants: (product.variants ?? []).map((v) => ({ ...v, cost_price: v.cost_price ?? null, barcode: v.barcode ?? '', initial_stock: '' })),
    modifier_group_ids: (product.modifier_groups ?? []).map((g) => g.id),
    outlet_prices: Object.fromEntries((product.outlet_prices ?? []).map((p) => [p.outlet_id, { sell_price: p.sell_price, is_available: p.is_available }])),
  })
}

/**
 * Form → payload POST/PUT /products.
 * Produk bervarian: harga utama = varian termurah ("mulai dari"); stok awal per varian baru
 * disimpan di outlet terpilih (fallbackOutletId) karena field outlet hanya tampil untuk produk tanpa varian.
 */
export function toPayload(form, { isEdit, fallbackOutletId }) {
  const { image, remove_image: removeImage, initial_stock: initialStock, outlet_id: outletId, variants, outlet_prices: outletPrices, ...data } = form

  const rows = variants.map((v) => ({
    id: v.id || null,
    name: v.name,
    sku: v.sku || null,
    barcode: v.barcode || null,
    sell_price: v.sell_price ?? 0,
    cost_price: v.cost_price ?? 0,
    is_active: v.is_active,
    initial_stock: Number(v.initial_stock) || null,
  }))
  const priced = rows.length
    ? { sell_price: Math.min(...rows.map((r) => r.sell_price)), cost_price: Math.min(...rows.map((r) => r.cost_price)) }
    : {}
  const variantStock = rows.some((r) => !r.id && r.initial_stock)
  const productStock = !isEdit && !rows.length && Number(initialStock) > 0

  return {
    ...data,
    cost_price: data.cost_price ?? 0,
    min_stock: Number(data.min_stock) || 0,
    ...priced, // setelah default di atas agar harga varian tidak tertimpa
    variants: rows,
    // Hanya outlet yang pernah diatur; baris default dihapus di server
    outlet_prices: Object.entries(outletPrices ?? {}).map(([id, p]) => ({ outlet_id: Number(id), sell_price: rows.length ? null : p.sell_price, is_available: p.is_available })),
    ...(productStock ? { initial_stock: Number(initialStock), outlet_id: outletId } : {}),
    ...(variantStock ? { outlet_id: outletId ?? fallbackOutletId } : {}),
    ...(image ? { image } : {}),
    ...(removeImage ? { remove_image: true } : {}),
  }
}
