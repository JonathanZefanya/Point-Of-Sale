import { computed, ref } from 'vue'
import http from '@/lib/http'
import { cacheGet, cachePut, isNetworkError } from '@/lib/offlineDb'

/**
 * Produk & kategori untuk layar kasir. Semua produk aktif dimuat sekali agar pencarian
 * terasa instan (< 500 ms, fitur-pos.md §23); scan barcode yang tidak ada di memori dicari ke server.
 * Harga & ketersediaan mengikuti outlet shift (2.8): produk yang tidak dijual di outlet ini tidak dimuat.
 *
 * @param {import('vue').Ref<number|null>} outletId
 */
export function useCatalog(outletId) {
  const products = ref([])
  const categories = ref([])
  const loading = ref(true)
  const error = ref('')
  const cachedAt = ref(null) // terisi bila katalog diambil dari cache perangkat (server tidak terjangkau)

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const [all, cats] = await Promise.all([fetchAllProducts(), http.get('/categories').then((r) => r.data.data)])
      products.value = all
      categories.value = cats
      cachedAt.value = null
      cachePut(cacheKey(), { products: all, categories: cats })
    } catch (e) {
      // Server tidak terjangkau → pakai katalog terakhir di perangkat (FE-3.1)
      const cached = isNetworkError(e) ? await cacheGet(cacheKey()) : null
      if (cached) {
        products.value = cached.value.products
        categories.value = cached.value.categories
        cachedAt.value = cached.saved_at
      } else {
        error.value = e.userMessage
      }
    } finally {
      loading.value = false
    }
  }

  const cacheKey = () => `catalog:${outletId?.value ?? 0}`

  async function fetchAllProducts() {
    const result = []
    for (let page = 1; page <= 50; page++) {
      const { data } = await http.get('/products', { params: { is_active: 1, per_page: 200, page, sort: 'name', outlet_id: outletId?.value ?? undefined, available_only: 1 } })
      result.push(...data.data)
      if (page >= data.meta.last_page) break
    }
    return result
  }

  /** Filter grid: kategori + kata kunci (nama, SKU, barcode). */
  function filter(categoryId, term) {
    const q = term.trim().toLowerCase()
    return products.value.filter((p) => {
      if (categoryId && p.category_id !== categoryId) return false
      if (!q) return true
      return p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || p.barcode === term.trim()
    })
  }

  /** Cocok persis barcode/SKU (hasil scan). Tidak ada di memori → tanya server. */
  async function findByCode(code) {
    const exact = products.value.find((p) => p.barcode === code || p.sku.toLowerCase() === code.toLowerCase())
    if (exact) return exact
    // Barcode/SKU varian → produknya + varian yang cocok
    for (const p of products.value) {
      const v = (p.variants ?? []).find((x) => x.is_active && (x.barcode === code || x.sku.toLowerCase() === code.toLowerCase()))
      if (v) return { ...p, matched_variant_id: v.id }
    }
    try {
      const { data } = await http.get(`/products/barcode/${encodeURIComponent(code)}`, { params: { outlet_id: outletId?.value ?? undefined }, silent: true })
      return data.data
    } catch {
      return null
    }
  }

  const usedCategories = computed(() => categories.value.filter((c) => products.value.some((p) => p.category_id === c.id)))

  return { products, categories: usedCategories, loading, error, cachedAt, load, filter, findByCode }
}
