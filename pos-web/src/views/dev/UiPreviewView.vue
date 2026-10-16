<script setup>
// Hanya untuk development: memeriksa komponen ui/ dalam BackofficeLayout.
import { ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Select from '@/components/ui/Select.vue'
import Table from '@/components/ui/Table.vue'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import { formatDateTime, formatRupiah } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()

const name = ref('Kopi Susu')
const sellPrice = ref(15000)
const costPrice = ref(18000)
const category = ref(null)
const priceError = ref('')
const modalOpen = ref(false)
const saving = ref(false)
const loading = ref(false)
const showEmpty = ref(false)

const categories = [
  { value: 1, label: 'Minuman' },
  { value: 2, label: 'Makanan' },
]

const columns = [
  { key: 'sku', label: 'SKU', mono: true, width: '120px' },
  { key: 'name', label: 'Produk' },
  { key: 'stock', label: 'Stok', align: 'right' },
  { key: 'price', label: 'Harga Jual', align: 'right' },
]
const rows = [
  { id: 1, sku: 'MNM-0001', name: 'Kopi Susu', stock: 42, price: 18000 },
  { id: 2, sku: 'MNM-0002', name: 'Teh Manis', stock: 3, price: 8000 },
  { id: 3, sku: 'MKN-0001', name: 'Roti Bakar', stock: 15, price: 18000 },
]

function validatePrice() {
  priceError.value = sellPrice.value < costPrice.value ? 'Harga jual lebih kecil dari harga modal.' : ''
}

function removeItem() {
  toast.show({ message: 'Kopi Susu dihapus', action: { label: 'Batalkan', onClick: () => toast.success('Kopi Susu dikembalikan') } })
}

function fakeSave() {
  saving.value = true
  setTimeout(() => {
    saving.value = false
    toast.success('Produk disimpan')
  }, 1200)
}

function reloadTable() {
  loading.value = true
  setTimeout(() => (loading.value = false), 1200)
}
</script>

<template>
  <BackofficeLayout>
    <div class="grid max-w-5xl gap-6">
      <section class="rounded border border-border bg-surface p-5">
        <h2 class="mb-4 font-semibold">Tombol</h2>
        <div class="flex flex-wrap items-center gap-2">
          <Button variant="primary" :loading="saving" loading-text="Menyimpan..." @click="fakeSave">Simpan Produk</Button>
          <Button>Batal</Button>
          <Button variant="danger" @click="modalOpen = true">Batalkan Transaksi</Button>
          <Button variant="ghost" @click="removeItem">Hapus Item (undo)</Button>
          <Button variant="primary" size="lg" shortcut="F9">Bayar</Button>
          <Button size="lg" shortcut="F4">Simpan</Button>
        </div>
      </section>

      <section class="rounded border border-border bg-surface p-5">
        <h2 class="mb-4 font-semibold">Form</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <Input v-model="name" label="Nama Produk" required />
          <Select v-model="category" label="Kategori" placeholder="Pilih kategori" :options="categories" />
          <MoneyInput v-model="costPrice" label="Harga Modal" @blur="validatePrice" />
          <MoneyInput v-model="sellPrice" label="Harga Jual" :error="priceError" hint="Harga yang dibayar pelanggan." @blur="validatePrice" />
        </div>
        <p class="mt-3 text-muted">
          Nilai tersimpan: <span class="font-mono">{{ sellPrice }}</span> → {{ formatRupiah(sellPrice) }} ·
          {{ formatDateTime(new Date()) }}
        </p>
      </section>

      <section class="rounded border border-border bg-surface p-5">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="font-semibold">Tabel</h2>
          <div class="flex gap-2">
            <Button @click="reloadTable">Muat ulang (skeleton)</Button>
            <Button @click="showEmpty = !showEmpty">{{ showEmpty ? 'Tampilkan data' : 'Kosongkan' }}</Button>
          </div>
        </div>
        <Table :columns="columns" :rows="showEmpty ? [] : rows" :loading="loading" clickable @row-click="(r) => toast.info(r.name)">
          <template #cell-stock="{ row }">
            <span :class="row.stock <= 5 && 'font-medium text-warning'">{{ row.stock }}</span>
          </template>
          <template #cell-price="{ value }">{{ formatRupiah(value) }}</template>
          <template #empty>
            <EmptyState title="Belum ada produk.">
              <Button variant="primary">Tambah Produk</Button>
              <Button>Import dari Excel</Button>
            </EmptyState>
          </template>
        </Table>
      </section>
    </div>

    <Modal v-model:open="modalOpen" title="Batalkan transaksi JKT01-20261015-0042?" size="sm">
      <p>Stok akan dikembalikan dan transaksi tidak bisa diaktifkan lagi.</p>
      <template #footer>
        <Button @click="modalOpen = false">Kembali</Button>
        <Button variant="danger" @click="modalOpen = false">Batalkan Transaksi</Button>
      </template>
    </Modal>
  </BackofficeLayout>
</template>
