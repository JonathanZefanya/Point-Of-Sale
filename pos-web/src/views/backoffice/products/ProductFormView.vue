<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import ProductFormFields from '@/components/products/ProductFormFields.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useProductApi } from '@/composables/useProductApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { blankForm, fromProduct, toPayload } from '@/lib/productForm'
import { useOutletStore } from '@/stores/outlet'
import { useToastStore } from '@/stores/toast'

/** Tambah (tanpa id) dan ubah produk (dengan id) memakai halaman yang sama — F7. */
const props = defineProps({
  id: { type: String, default: null },
})

const api = useProductApi()
const toast = useToastStore()
const outletStore = useOutletStore()
const router = useRouter()


const isEdit = computed(() => !!props.id)
const form = ref(blankForm())
const errors = ref({})
const original = ref(null)
const categories = ref([])
const modifierGroups = ref([])
const loading = ref(true)
const loadError = ref('')
const saving = ref(null) // 'save' | 'save-new' | null
const confirmDelete = ref(false)
const deleting = ref(false)
const fields = ref(null)
const formKey = ref(0) // remount field agar state lokal (pratinjau gambar, dll.) ikut bersih

function payload() {
  return toPayload(form.value, { isEdit: isEdit.value, fallbackOutletId: outletStore.current?.id })
}

const pageTitle = computed(() => (isEdit.value ? `Ubah Produk${original.value ? ` · ${original.value.name}` : ''}` : 'Tambah Produk'))

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [categoryList, groups, product] = await Promise.all([
      api.categories(),
      api.modifierGroups(),
      isEdit.value ? api.get(props.id) : null,
      outletStore.ensureLoaded(),
    ])
    categories.value = categoryList
    modifierGroups.value = groups
    if (product) {
      original.value = product
      form.value = fromProduct(product)
    }
  } catch (error) {
    loadError.value = error.status === 404 ? 'Produk ini tidak ditemukan. Mungkin sudah dihapus.' : error.userMessage
  } finally {
    loading.value = false
  }
}


async function save(mode) {
  if (!fields.value.validateAll()) return

  saving.value = mode
  try {
    const product = isEdit.value ? await api.update(props.id, payload()) : await api.create(payload())
    toast.success(`Produk ${product.name} disimpan dengan SKU ${product.sku}.`)

    if (mode === 'save-new') {
      // Kategori dipertahankan: biasanya produk berikutnya satu kategori
      form.value = blankForm({ category_id: form.value.category_id, outlet_id: form.value.outlet_id })
      errors.value = {}
      formKey.value++
      await nextTick()
      fields.value?.focusName()
    } else {
      router.push({ name: 'products' })
    }
  } catch (error) {
    if (error.fieldErrors) errors.value = error.fieldErrors
  } finally {
    saving.value = null
  }
}

async function remove() {
  deleting.value = true
  try {
    await api.remove(props.id)
    confirmDelete.value = false
    toast.success(`Produk ${original.value.name} dihapus.`)
    router.push({ name: 'products' })
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<template>
  <BackofficeLayout :title="pageTitle">
    <div class="max-w-3xl">
      <RouterLink :to="{ name: 'products' }" class="mb-3 inline-block text-muted hover:text-text">← Kembali ke daftar produk</RouterLink>

      <div v-if="loading" class="grid gap-4 rounded border border-border bg-surface p-5" aria-busy="true">
        <Skeleton v-for="n in 6" :key="n" height="2.25rem" />
      </div>

      <div v-else-if="loadError" class="rounded border border-border bg-surface">
        <EmptyState title="Produk tidak bisa dibuka." :description="loadError">
          <Button variant="primary" @click="router.push({ name: 'products' })">Kembali ke Daftar</Button>
          <Button @click="load">Coba Lagi</Button>
        </EmptyState>
      </div>

      <form v-else class="rounded border border-border bg-surface" novalidate @submit.prevent="save('save')">
        <div class="p-5">
          <ProductFormFields
            :key="formKey"
            ref="fields"
            v-model="form"
            v-model:errors="errors"
            :categories="categories"
            :current-image-url="original?.image_url ?? null"
            :is-edit="isEdit"
            :modifier-groups="modifierGroups"
            @category-created="categories.push($event)"
          />
        </div>

        <footer class="flex items-center justify-between gap-2 border-t border-border px-5 py-3">
          <Button v-if="isEdit" variant="danger-ghost" @click="confirmDelete = true">Hapus Produk</Button>
          <span v-else />
          <div class="flex gap-2">
            <Button :disabled="!!saving" @click="router.push({ name: 'products' })">Batal</Button>
            <Button v-if="!isEdit" :loading="saving === 'save-new'" loading-text="Menyimpan..." :disabled="!!saving" @click="save('save-new')">
              Simpan &amp; Tambah Lagi
            </Button>
            <Button type="submit" variant="primary" :loading="saving === 'save'" loading-text="Menyimpan..." :disabled="!!saving">
              Simpan Produk
            </Button>
          </div>
        </footer>
      </form>
    </div>

    <ConfirmModal
      v-if="original"
      v-model:open="confirmDelete"
      :title="`Hapus produk ${original.name}?`"
      confirm-label="Hapus Produk"
      busy-label="Menghapus..."
      :busy="deleting"
      @confirm="remove"
    >
      <p>Produk tidak bisa dijual lagi dan hilang dari daftar. Riwayat penjualan dan kartu stoknya tetap tersimpan.</p>
      <p class="text-muted">Jika hanya ingin berhenti menjual sementara, hilangkan centang "Dijual".</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
