<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import http from '@/lib/http'
import { formatDateTime } from '@/lib/format'
import { useToastStore } from '@/stores/toast'

/** Absen masuk/pulang diri sendiri (3.7). Membuka shift sudah otomatis absen masuk. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  outletId: { type: Number, default: null },
})

const toast = useToastStore()
const current = ref(null)
const loading = ref(false)
const saving = ref(false)
const note = ref('')

const duration = computed(() => {
  if (!current.value) return ''
  const m = current.value.minutes
  return `${Math.floor(m / 60)} jam ${m % 60} menit`
})

watch(open, async (isOpen) => {
  if (!isOpen) return
  note.value = ''
  loading.value = true
  try {
    current.value = (await http.get('/attendances/current')).data.data
  } finally {
    loading.value = false
  }
})

async function submit() {
  saving.value = true
  try {
    if (current.value) {
      await http.post('/attendances/clock-out', { note: note.value || null })
      toast.success('Absen pulang tercatat. Sampai jumpa!')
    } else {
      await http.post('/attendances/clock-in', { outlet_id: props.outletId, note: note.value || null })
      toast.success('Absen masuk tercatat.')
    }
    open.value = false
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" title="Absensi" size="sm" :dismissible="!saving">
    <form id="attendance-form" class="cashier-root flex flex-col gap-3" novalidate @submit.prevent="submit">
      <p v-if="loading" class="text-muted">Memuat status absensi...</p>
      <template v-else>
        <p v-if="current">
          Masuk sejak <span class="font-medium">{{ formatDateTime(current.clock_in_at) }}</span> di {{ current.outlet?.name }}
          <span class="block text-muted">Sudah bekerja {{ duration }}.</span>
        </p>
        <p v-else class="text-muted">Anda belum absen masuk hari ini.</p>
        <Input v-model="note" label="Catatan (opsional)" maxlength="255" size="lg" :placeholder="current ? 'mis. pulang lebih awal karena sakit' : 'mis. datang terlambat karena hujan'" />
      </template>
    </form>
    <template #footer>
      <Button size="lg" :disabled="saving" @click="open = false">Tutup</Button>
      <Button type="submit" form="attendance-form" variant="primary" size="lg" :disabled="loading" :loading="saving" loading-text="Menyimpan...">
        {{ current ? 'Absen Pulang' : 'Absen Masuk' }}
      </Button>
    </template>
  </Modal>
</template>
