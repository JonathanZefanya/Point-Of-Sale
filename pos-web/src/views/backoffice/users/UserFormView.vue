<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import DeleteUserModal from '@/components/users/DeleteUserModal.vue'
import UserFormFields from '@/components/users/UserFormFields.vue'
import { useUserApi } from '@/composables/useUserApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { ROLE } from '@/lib/roles'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

/** Tambah (tanpa id) dan ubah karyawan (dengan id) memakai halaman yang sama. */
const props = defineProps({
  id: { type: String, default: null },
})

const api = useUserApi()
const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const isEdit = computed(() => !!props.id)
const isSelf = computed(() => isEdit.value && Number(props.id) === auth.user?.id)

const form = ref({ name: '', email: '', role: ROLE.CASHIER, password: '', pin: '', outlet_ids: [], is_active: true, commission_percent: 0 })
const errors = ref({})
const original = ref(null)
const roles = ref([])
const outlets = ref([])
const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const confirmDelete = ref(false)
const deleting = ref(false)
const fields = ref(null)

const pageTitle = computed(() => (isEdit.value ? `Ubah Karyawan${original.value ? ` · ${original.value.name}` : ''}` : 'Tambah Karyawan'))
const canDelete = computed(() => isEdit.value && !isSelf.value)

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [roleList, outletList, user] = await Promise.all([
      api.roles(),
      api.outlets(),
      isEdit.value ? api.get(props.id) : Promise.resolve(null),
    ])
    roles.value = roleList
    outlets.value = outletList

    if (user) {
      original.value = user
      form.value = {
        name: user.name,
        email: user.email,
        role: user.role,
        password: '',
        pin: '',
        outlet_ids: user.outlets.map((o) => o.id),
        is_active: user.is_active,
        commission_percent: user.commission_percent ?? 0,
      }
    } else if (outletList.length === 1) {
      form.value.outlet_ids = [outletList[0].id] // default masuk akal: satu-satunya outlet
    }
  } catch (error) {
    loadError.value = error.status === 404 ? 'Karyawan ini tidak ditemukan. Mungkin sudah dihapus.' : error.userMessage
  } finally {
    loading.value = false
  }
}

function payload() {
  const data = { ...form.value }
  if (data.role === ROLE.OWNER) data.outlet_ids = []
  if (!isEdit.value) delete data.is_active
  return data
}

async function save() {
  if (!fields.value.validateAll()) return

  saving.value = true
  try {
    if (isEdit.value) await api.update(props.id, payload())
    else await api.create(payload())
    toast.success(`Karyawan ${form.value.name} disimpan.`)
    router.push({ name: 'users' })
  } catch (error) {
    if (error.fieldErrors) errors.value = mapFieldErrors(error.fieldErrors)
  } finally {
    saving.value = false
  }
}

/** "outlet_ids.0" dari server → error di grup outlet */
function mapFieldErrors(fieldErrors) {
  const mapped = {}
  for (const [key, message] of Object.entries(fieldErrors)) {
    mapped[key.split('.')[0]] ??= message
  }
  return mapped
}

async function remove() {
  deleting.value = true
  try {
    await api.remove(props.id)
    confirmDelete.value = false
    toast.success(`Karyawan ${original.value.name} dihapus.`)
    router.push({ name: 'users' })
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<template>
  <BackofficeLayout :title="pageTitle">
    <div class="max-w-3xl">
      <RouterLink :to="{ name: 'users' }" class="mb-3 inline-block text-muted hover:text-text">← Kembali ke daftar karyawan</RouterLink>

      <div v-if="loading" class="grid gap-4 rounded border border-border bg-surface p-5" aria-busy="true">
        <Skeleton v-for="n in 5" :key="n" height="2.25rem" />
      </div>

      <div v-else-if="loadError" class="rounded border border-border bg-surface">
        <EmptyState title="Data karyawan tidak bisa dibuka." :description="loadError">
          <Button variant="primary" @click="router.push({ name: 'users' })">Kembali ke Daftar</Button>
          <Button @click="load">Coba Lagi</Button>
        </EmptyState>
      </div>

      <form v-else class="rounded border border-border bg-surface" novalidate @submit.prevent="save">
        <div class="p-5">
          <UserFormFields
            ref="fields"
            v-model="form"
            v-model:errors="errors"
            :roles="roles"
            :outlets="outlets"
            :is-edit="isEdit"
            :is-self="isSelf"
            :has-pin="original?.has_pin ?? false"
          />
        </div>

        <footer class="flex items-center justify-between gap-2 border-t border-border px-5 py-3">
          <Button v-if="canDelete" variant="danger-ghost" @click="confirmDelete = true">Hapus Karyawan</Button>
          <span v-else />
          <div class="flex gap-2">
            <Button :disabled="saving" @click="router.push({ name: 'users' })">Batal</Button>
            <Button type="submit" variant="primary" :loading="saving" loading-text="Menyimpan...">Simpan Karyawan</Button>
          </div>
        </footer>
      </form>
    </div>

    <DeleteUserModal v-if="original" v-model:open="confirmDelete" :name="original.name" :deleting="deleting" @confirm="remove" />
  </BackofficeLayout>
</template>
