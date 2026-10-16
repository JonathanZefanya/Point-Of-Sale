<script setup>
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Pagination from '@/components/ui/Pagination.vue'
import UserFilters from '@/components/users/UserFilters.vue'
import UserTable from '@/components/users/UserTable.vue'
import { useListQuery, usePaginatedList } from '@/composables/useListQuery'
import { useUserApi } from '@/composables/useUserApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'

const api = useUserApi()
const router = useRouter()

const { filters, page, hasFilter, setPage, resetFilters } = useListQuery({ search: '', role: '', is_active: '' })
const { rows: users, meta, loading, error, load } = usePaginatedList(api.list, () => ({
  ...filters.value,
  page: page.value,
  per_page: 20,
  sort: 'name',
}))

function openUser(user) {
  router.push({ name: 'user-edit', params: { id: user.id } })
}
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" @click="router.push({ name: 'user-create' })">Tambah Karyawan</Button>
    </template>

    <div class="flex flex-col gap-3">
      <UserFilters v-model="filters" />

      <div v-if="error" class="rounded border border-border bg-surface">
        <EmptyState title="Daftar karyawan gagal dimuat." :description="error">
          <Button variant="primary" @click="load">Coba Lagi</Button>
        </EmptyState>
      </div>

      <template v-else>
        <UserTable :users="users" :loading="loading" @open="openUser">
          <template #empty>
            <EmptyState
              v-if="hasFilter"
              title="Tidak ada karyawan yang cocok dengan filter."
              description="Coba kata kunci lain atau hapus filter."
            >
              <Button @click="resetFilters">Hapus Filter</Button>
            </EmptyState>
            <EmptyState
              v-else
              title="Belum ada karyawan."
              description="Tambahkan kasir agar mereka bisa login di perangkat kasir dengan PIN."
            >
              <Button variant="primary" @click="router.push({ name: 'user-create' })">Tambah Karyawan</Button>
            </EmptyState>
          </template>
        </UserTable>

        <Pagination :meta="meta" :disabled="loading" @change="setPage" />
      </template>
    </div>
  </BackofficeLayout>
</template>
