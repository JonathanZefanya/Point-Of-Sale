<script setup>
import { ref, watch } from 'vue'
import Badge from '@/components/ui/Badge.vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import { useCustomerApi } from '@/composables/useCustomerApi'
import { formatDateTime, formatNumber, formatRupiah } from '@/lib/format'

/** Ringkasan & riwayat belanja satu pelanggan (2.3). */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  customer: { type: Object, default: null },
})

const emit = defineEmits(['edit'])

const api = useCustomerApi()
const sales = ref([])
const loading = ref(false)

watch(open, async (isOpen) => {
  if (!isOpen || !props.customer) return
  loading.value = true
  try {
    sales.value = (await api.sales(props.customer.id, { per_page: 50 })).data
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <Modal v-model:open="open" :title="customer?.name ?? ''" size="lg">
    <div v-if="customer" class="grid gap-4">
      <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div><dt class="text-muted">Nomor HP</dt><dd>{{ customer.phone || '-' }}</dd></div>
        <div><dt class="text-muted">Grup</dt><dd>{{ customer.group_label }}</dd></div>
        <div><dt class="text-muted">Kunjungan</dt><dd class="tabular-nums">{{ formatNumber(customer.visits ?? 0) }}</dd></div>
        <div><dt class="text-muted">Total belanja</dt><dd class="tabular-nums">{{ formatRupiah(customer.total_spent ?? 0) }}</dd></div>
      </dl>

      <section>
        <h3 class="mb-2 font-semibold">Riwayat belanja</h3>
        <div v-if="loading" class="grid gap-2" aria-busy="true"><Skeleton v-for="n in 4" :key="n" height="2.5rem" /></div>
        <p v-else-if="!sales.length" class="text-muted">Belum ada transaksi atas nama pelanggan ini.</p>
        <ul v-else class="divide-y divide-border rounded border border-border">
          <li v-for="s in sales" :key="s.id" class="flex items-center gap-3 px-3 py-2">
            <span class="w-40 text-muted">{{ formatDateTime(s.completed_at) }}</span>
            <span class="flex-1 font-mono">{{ s.number }}</span>
            <Badge v-if="s.status !== 'completed'" :tone="s.status === 'void' ? 'danger' : 'warning'">{{ s.status_label }}</Badge>
            <span class="num w-32">{{ formatRupiah(s.grand_total) }}</span>
          </li>
        </ul>
      </section>
    </div>
    <template #footer>
      <Button @click="emit('edit', customer)">Ubah Data</Button>
      <Button variant="primary" @click="open = false">Tutup</Button>
    </template>
  </Modal>
</template>
