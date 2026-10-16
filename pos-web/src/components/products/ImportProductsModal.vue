<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import { useProductApi } from '@/composables/useProductApi'
import { formatNumber, formatRupiah } from '@/lib/format'
import { useOutletStore } from '@/stores/outlet'
import { useToastStore } from '@/stores/toast'

/**
 * Import produk (F7.4): unduh template → pilih file → pratinjau + error per baris → konfirmasi.
 * Tidak ada yang disimpan sampai tombol Import ditekan, dan hanya bila semua baris benar.
 */
const open = defineModel('open', { type: Boolean, default: false })
const emit = defineEmits(['imported'])

const api = useProductApi()
const outletStore = useOutletStore()
const toast = useToastStore()

const file = ref(null)
const preview = ref(null)
const busy = ref(false)
const error = ref('')
const onlyProblems = ref(true)
const input = ref(null)

const summary = computed(() => preview.value?.summary)
const rows = computed(() => (preview.value?.rows ?? []).filter((r) => !onlyProblems.value || r.errors.length || r.warnings.length))

watch(open, (isOpen) => {
  if (!isOpen) return
  file.value = null
  preview.value = null
  error.value = ''
  onlyProblems.value = true
})

async function check(selected) {
  if (!selected) return
  file.value = selected
  busy.value = true
  error.value = ''
  preview.value = null
  try {
    preview.value = await api.importFile(selected, outletStore.current?.id, true)
    onlyProblems.value = preview.value.summary.errors > 0
  } catch (e) {
    error.value = e.fieldErrors?.file ?? e.userMessage
  } finally {
    busy.value = false
    if (input.value) input.value.value = ''
  }
}

async function commit() {
  busy.value = true
  error.value = ''
  try {
    const result = await api.importFile(file.value, outletStore.current?.id, false)
    toast.success(`${formatNumber(result.summary.create)} produk baru ditambahkan, ${formatNumber(result.summary.update)} diperbarui.`)
    open.value = false
    emit('imported')
  } catch (e) {
    if (e.response?.data?.rows) preview.value = e.response.data
    error.value = e.userMessage
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" title="Import produk dari Excel" size="lg" :dismissible="!busy">
    <div class="flex flex-col gap-4">
      <ol class="list-decimal space-y-1 pl-5">
        <li>Unduh template, isi satu produk per baris. <button type="button" class="font-medium text-primary underline" @click="api.downloadTemplate()">Unduh template</button></li>
        <li>Pilih file .xlsx atau .csv. Produk dengan SKU yang sudah ada akan diperbarui.</li>
        <li>Periksa pratinjau, lalu tekan Import.</li>
      </ol>

      <div class="flex flex-wrap items-center gap-3">
        <input id="import-file" ref="input" type="file" accept=".xlsx,.csv" class="sr-only" @change="check($event.target.files[0])">
        <label for="import-file" class="inline-flex h-9 cursor-pointer items-center rounded border border-border-strong bg-surface px-3.5 text-sm font-medium hover:bg-surface-muted">
          {{ file ? 'Pilih file lain' : 'Pilih file' }}
        </label>
        <span v-if="file" class="text-muted">{{ file.name }}</span>
        <span v-if="busy && !preview" class="text-muted">Memeriksa file...</span>
      </div>

      <template v-if="summary">
        <dl class="grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
          <div class="rounded border border-border p-2"><dt class="text-muted">Baris</dt><dd class="text-xl font-semibold tabular-nums">{{ formatNumber(summary.total) }}</dd></div>
          <div class="rounded border border-border p-2"><dt class="text-muted">Produk baru</dt><dd class="text-xl font-semibold tabular-nums">{{ formatNumber(summary.create) }}</dd></div>
          <div class="rounded border border-border p-2"><dt class="text-muted">Diperbarui</dt><dd class="text-xl font-semibold tabular-nums">{{ formatNumber(summary.update) }}</dd></div>
          <div class="rounded border border-border p-2">
            <dt class="text-muted">Perlu diperbaiki</dt>
            <dd :class="['text-xl font-semibold tabular-nums', summary.errors && 'text-danger']">{{ formatNumber(summary.errors) }}</dd>
          </div>
        </dl>
        <p v-if="summary.new_categories.length" class="text-muted">Kategori baru akan dibuat: {{ summary.new_categories.join(', ') }}.</p>
        <p v-if="summary.errors" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">
          Perbaiki baris yang ditandai di Excel, simpan, lalu pilih file lagi. Tidak ada produk yang disimpan sebelum semua baris benar.
        </p>

        <label class="flex items-center gap-2"><input v-model="onlyProblems" type="checkbox" class="accent-primary"> Tampilkan hanya baris bermasalah</label>
        <div class="max-h-80 overflow-y-auto rounded border border-border">
          <table class="w-full text-left text-sm">
            <thead class="sticky top-0 border-b border-border bg-surface text-muted">
              <tr><th class="px-3 py-2 font-medium">Baris</th><th class="px-3 py-2 font-medium">Produk</th><th class="px-3 py-2 text-right font-medium">Harga jual</th><th class="px-3 py-2 font-medium">Keterangan</th></tr>
            </thead>
            <tbody class="divide-y divide-border">
              <tr v-for="r in rows" :key="r.row" :class="r.errors.length && 'bg-danger-soft'">
                <td class="px-3 py-2 tabular-nums">{{ r.row }}</td>
                <td class="px-3 py-2">
                  {{ r.data.name || '–' }}
                  <span class="block text-muted">{{ r.action === 'update' ? `Perbarui ${r.data.sku}` : 'Produk baru' }}</span>
                </td>
                <td class="num px-3 py-2">{{ r.data.sell === null ? '–' : formatRupiah(r.data.sell) }}</td>
                <td class="px-3 py-2">
                  <p v-for="m in r.errors" :key="m" class="text-danger">{{ m }}</p>
                  <p v-for="m in r.warnings" :key="m" class="text-warning">{{ m }}</p>
                  <p v-if="!r.errors.length && !r.warnings.length" class="text-success">Siap</p>
                </td>
              </tr>
              <tr v-if="!rows.length"><td colspan="4" class="px-3 py-4 text-center text-muted">Semua baris siap di-import.</td></tr>
            </tbody>
          </table>
        </div>
      </template>
      <p v-if="error" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">{{ error }}</p>
    </div>

    <template #footer>
      <Button :disabled="busy" @click="open = false">Batal</Button>
      <Button variant="primary" :disabled="!summary || summary.errors > 0" :loading="busy && !!summary" loading-text="Mengimport..." @click="commit">
        Import {{ summary ? formatNumber(summary.total) : '' }} Produk
      </Button>
    </template>
  </Modal>
</template>
