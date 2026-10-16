<script setup>
import { onMounted, ref } from 'vue'
import ModifierGroupModal from '@/components/products/ModifierGroupModal.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Table from '@/components/ui/Table.vue'
import { useProductApi } from '@/composables/useProductApi'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatNumber, formatRupiah } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/** Grup modifier/add-on (2.1), mis. "Level Gula" atau "Topping", dipakai di banyak produk. */
const api = useProductApi()
const toast = useToastStore()

const groups = ref([])
const loading = ref(true)
const error = ref('')
const editing = ref(null)
const formOpen = ref(false)
const deleting = ref(null)
const deleteBusy = ref(false)

const columns = [
  { key: 'name', label: 'Grup' },
  { key: 'rule', label: 'Aturan', width: '180px' },
  { key: 'modifiers', label: 'Pilihan' },
  { key: 'products_count', label: 'Dipakai', align: 'right', width: '110px' },
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    groups.value = await api.modifierGroups()
  } catch (e) {
    error.value = e.userMessage
  } finally {
    loading.value = false
  }
}

function edit(group) {
  editing.value = group
  formOpen.value = true
}

async function remove() {
  deleteBusy.value = true
  try {
    await api.removeModifierGroup(deleting.value.id)
    toast.success(`Grup ${deleting.value.name} dihapus.`)
    deleting.value = null
    formOpen.value = false
    load()
  } finally {
    deleteBusy.value = false
  }
}

const rule = (g) => (g.min_select > 0 ? `Wajib, ${g.min_select}–${g.max_select} pilihan` : `Opsional, maks ${g.max_select}`)
const options = (g) => g.modifiers.map((m) => (m.price ? `${m.name} (+${formatRupiah(m.price)})` : m.name)).join(', ')

onMounted(load)
</script>

<template>
  <BackofficeLayout>
    <template #header-actions>
      <Button variant="primary" @click="edit(null)">Tambah Grup Modifier</Button>
    </template>

    <div v-if="error" class="max-w-xl rounded border border-border bg-surface">
      <EmptyState title="Grup modifier gagal dimuat." :description="error"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>

    <Table v-else :columns="columns" :rows="groups" :loading="loading" clickable @row-click="edit">
      <template #cell-name="{ row }"><span class="font-medium">{{ row.name }}</span></template>
      <template #cell-rule="{ row }">{{ rule(row) }}</template>
      <template #cell-modifiers="{ row }"><span class="text-muted">{{ options(row) }}</span></template>
      <template #cell-products_count="{ value }">{{ formatNumber(value) }} produk</template>
      <template #empty>
        <EmptyState title="Belum ada grup modifier." description="Contoh: &quot;Level Gula&quot; (wajib pilih 1) atau &quot;Topping&quot; (opsional, tambah harga). Setelah dibuat, pasang di produk lewat Pengaturan lanjutan.">
          <Button variant="primary" @click="edit(null)">Tambah Grup Modifier</Button>
        </EmptyState>
      </template>
    </Table>

    <ModifierGroupModal v-model:open="formOpen" :group="editing" @saved="load" @delete="(g) => { formOpen = false; deleting = g }" />
    <ConfirmModal
      :open="!!deleting"
      :title="deleting ? `Hapus grup ${deleting.name}?` : ''"
      confirm-label="Hapus Grup"
      busy-label="Menghapus..."
      :busy="deleteBusy"
      @update:open="(v) => !v && (deleting = null)"
      @confirm="remove"
    >
      <p>Grup dilepas dari {{ deleting?.products_count ?? 0 }} produk. Riwayat penjualan tetap menyimpan nama pilihan yang pernah dijual.</p>
    </ConfirmModal>
  </BackofficeLayout>
</template>
