import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

/** Buang nilai kosong agar query string & parameter API tetap bersih. */
export function cleanQuery(query) {
  return Object.fromEntries(Object.entries(query).filter(([, v]) => v !== '' && v !== undefined && v !== null))
}

/**
 * Filter & halaman daftar disimpan di URL, sehingga tombol Kembali dari form
 * mengembalikan daftar yang sama dan link bisa dibagikan.
 *
 * @param {Record<string, string>} defaults mis. { search: '', role: '', is_active: '' }
 */
export function useListQuery(defaults) {
  const route = useRoute()
  const router = useRouter()

  const filters = computed({
    get: () => Object.fromEntries(Object.entries(defaults).map(([key, value]) => [key, route.query[key] ?? value])),
    // Ganti filter → kembali ke halaman 1
    set: (value) => router.replace({ query: cleanQuery({ ...value, page: undefined }) }),
  })

  const page = computed(() => Number(route.query.page ?? 1))
  const hasFilter = computed(() => Object.entries(filters.value).some(([key, value]) => value !== defaults[key]))

  function setPage(value) {
    router.replace({ query: { ...route.query, page: value } })
  }

  function resetFilters() {
    filters.value = { ...defaults }
  }

  return { filters, page, hasFilter, setPage, resetFilters, query: computed(() => route.query) }
}

/**
 * State daftar berpaginasi + memuat ulang otomatis saat query URL berubah.
 * Respons lama diabaikan bila filter sudah berubah lagi sebelum respons tiba.
 *
 * @param {(params: object) => Promise<{data: Array, meta: object}>} fetcher
 * @param {() => object} params parameter API dari filter/halaman saat ini
 */
export function usePaginatedList(fetcher, params) {
  const route = useRoute()
  const rows = ref([])
  const meta = ref(null)
  const extra = ref({}) // field lain di respons, mis. summary (stok) atau product (kartu stok)
  const loading = ref(true)
  const error = ref('')
  let current = 0

  async function load() {
    const id = ++current
    loading.value = true
    error.value = ''
    try {
      const result = await fetcher(cleanQuery(params()))
      if (id !== current) return
      const { data, meta: resultMeta, ...rest } = result
      rows.value = data
      meta.value = resultMeta
      extra.value = rest
    } catch (e) {
      if (id === current) error.value = e.userMessage
    } finally {
      if (id === current) loading.value = false
    }
  }

  watch(() => route.query, load, { immediate: true })

  return { rows, meta, extra, loading, error, load }
}
