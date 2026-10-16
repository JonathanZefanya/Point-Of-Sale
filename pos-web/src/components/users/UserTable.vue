<script setup>
import Badge from '@/components/ui/Badge.vue'
import Table from '@/components/ui/Table.vue'
import { useAuthStore } from '@/stores/auth'

defineProps({
  users: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['open'])
const auth = useAuthStore()

const columns = [
  { key: 'name', label: 'Nama' },
  { key: 'role', label: 'Peran', width: '150px' },
  { key: 'outlets', label: 'Outlet' },
  { key: 'has_pin', label: 'PIN Kasir', width: '110px' },
  { key: 'is_active', label: 'Status', width: '110px' },
]
</script>

<template>
  <Table :columns="columns" :rows="users" :loading="loading" clickable @row-click="emit('open', $event)">
    <template #cell-name="{ row }">
      <span class="font-medium">{{ row.name }}</span>
      <span v-if="row.id === auth.user?.id" class="ml-1.5 text-muted">(Anda)</span>
      <span class="block text-muted">{{ row.email }}</span>
    </template>
    <template #cell-role="{ row }">{{ row.role_label }}</template>
    <template #cell-outlets="{ row }">
      <span v-if="row.role === 'owner'" class="text-muted">Semua outlet</span>
      <span v-else-if="!row.outlets?.length" class="text-warning">Belum ada outlet</span>
      <span v-else class="font-mono">{{ row.outlets.map((o) => o.code).join(', ') }}</span>
    </template>
    <template #cell-has_pin="{ row }">
      <span :class="row.has_pin ? 'text-text' : 'text-muted'">{{ row.has_pin ? 'Ada' : 'Belum' }}</span>
    </template>
    <template #cell-is_active="{ row }">
      <Badge :tone="row.is_active ? 'success' : 'neutral'">{{ row.is_active ? 'Aktif' : 'Nonaktif' }}</Badge>
    </template>
    <template #empty>
      <slot name="empty" />
    </template>
  </Table>
</template>
