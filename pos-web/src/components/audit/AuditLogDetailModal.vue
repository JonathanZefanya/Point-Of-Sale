<script setup>
import { computed } from 'vue'
import Modal from '@/components/ui/Modal.vue'
import { formatDateTime } from '@/lib/format'

/** Rincian satu audit log: nilai sebelum → sesudah per field. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  log: { type: Object, default: null },
})

const show = (v) => (v === null || v === undefined ? '–' : typeof v === 'object' ? JSON.stringify(v) : String(v))

const rows = computed(() => {
  const before = props.log?.before ?? {}
  const after = props.log?.after ?? {}
  const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])]
  return keys.map((key) => ({ key, before: show(before[key]), after: show(after[key]), changed: show(before[key]) !== show(after[key]) }))
})
</script>

<template>
  <Modal v-model:open="open" :title="log?.action_label ?? 'Rincian log'" size="md">
    <div v-if="log" class="flex flex-col gap-3">
      <dl class="grid grid-cols-2 gap-2">
        <div><dt class="text-muted">Waktu</dt><dd>{{ formatDateTime(log.created_at) }}</dd></div>
        <div><dt class="text-muted">Pengguna</dt><dd>{{ log.user?.name ?? 'Sistem' }}</dd></div>
        <div><dt class="text-muted">Data</dt><dd>{{ log.subject ?? '–' }}</dd></div>
        <div><dt class="text-muted">Alamat IP</dt><dd class="font-mono text-sm">{{ log.ip ?? '–' }}</dd></div>
      </dl>
      <table v-if="rows.length" class="w-full text-left text-sm">
        <thead class="border-b border-border text-muted">
          <tr><th class="py-1.5 pr-3 font-medium">Field</th><th class="py-1.5 pr-3 font-medium">Sebelum</th><th class="py-1.5 font-medium">Sesudah</th></tr>
        </thead>
        <tbody class="divide-y divide-border">
          <tr v-for="r in rows" :key="r.key" :class="r.changed && 'font-medium'">
            <td class="py-1.5 pr-3 font-mono">{{ r.key }}</td>
            <td class="break-all py-1.5 pr-3">{{ r.before }}</td>
            <td class="break-all py-1.5">{{ r.after }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else class="text-muted">Tidak ada rincian data untuk aksi ini.</p>
    </div>
  </Modal>
</template>
