<script setup>
import { nextTick, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import { useCustomerApi } from '@/composables/useCustomerApi'
import { formatNumber } from '@/lib/format'

/** Pilih pelanggan di kasir: cari nama/nomor HP, atau tambah baru (nama + HP) tanpa keluar dari kasir. */
const open = defineModel('open', { type: Boolean, default: false })

const emit = defineEmits(['select'])

const api = useCustomerApi()
const search = ref('')
const results = ref([])
const loading = ref(false)
const adding = ref(false)
const form = ref({ name: '', phone: '' })
const errors = ref({})
const saving = ref(false)
const searchInput = ref(null)

let timer = null
let requestId = 0
async function load(term) {
  const id = ++requestId
  loading.value = true
  try {
    const result = await api.list({ search: term.trim() || undefined, per_page: 10 })
    if (id === requestId) results.value = result.data
  } finally {
    if (id === requestId) loading.value = false
  }
}

watch(search, (term) => {
  clearTimeout(timer)
  timer = setTimeout(() => load(term), 250)
})

watch(open, async (isOpen) => {
  if (!isOpen) return
  adding.value = false
  search.value = ''
  form.value = { name: '', phone: '' }
  errors.value = {}
  load('')
  await nextTick()
  searchInput.value?.focus()
})

function choose(customer) {
  emit('select', customer)
  open.value = false
}

function startAdd() {
  adding.value = true
  // Ketikan berupa angka → kemungkinan nomor HP, selain itu nama
  form.value = /^[\d+\s-]+$/.test(search.value) ? { name: '', phone: search.value } : { name: search.value, phone: '' }
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    choose(await api.save({ name: form.value.name, phone: form.value.phone || null }))
  } catch (e) {
    if (e.fieldErrors) errors.value = e.fieldErrors
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" title="Pilih pelanggan" size="md">
    <div class="cashier-root grid gap-3">
      <template v-if="!adding">
        <Input ref="searchInput" v-model="search" type="search" size="lg" placeholder="Cari nama atau nomor HP" aria-label="Cari pelanggan" />
        <ul v-if="results.length" class="divide-y divide-border rounded border border-border">
          <li v-for="c in results" :key="c.id">
            <button type="button" class="flex min-h-touch w-full items-center gap-3 px-3 py-2 text-left hover:bg-surface-muted" @click="choose(c)">
              <span class="min-w-0 flex-1">
                <span class="block font-medium">{{ c.name }}</span>
                <span class="block text-sm text-muted">{{ c.phone || 'Tanpa nomor HP' }} · {{ c.group_label }}</span>
              </span>
              <span class="text-sm tabular-nums text-muted">{{ formatNumber(c.points) }} poin</span>
            </button>
          </li>
        </ul>
        <p v-else-if="search && !loading" class="text-muted">Pelanggan tidak ditemukan.</p>
        <Button size="lg" @click="startAdd">+ Tambah Pelanggan Baru</Button>
      </template>

      <form v-else id="customer-quick-form" class="grid gap-3" novalidate @submit.prevent="save">
        <Input v-model="form.name" label="Nama" size="lg" required :error="errors.name" />
        <Input v-model="form.phone" label="Nomor HP" size="lg" inputmode="tel" placeholder="081234567890" :error="errors.phone" />
      </form>
    </div>
    <template #footer>
      <template v-if="adding">
        <Button size="lg" :disabled="saving" @click="adding = false">Kembali</Button>
        <Button type="submit" form="customer-quick-form" variant="primary" size="lg" :loading="saving" loading-text="Menyimpan...">Simpan &amp; Pilih</Button>
      </template>
      <Button v-else size="lg" @click="choose(null)">Tanpa Pelanggan (Umum)</Button>
    </template>
  </Modal>
</template>
