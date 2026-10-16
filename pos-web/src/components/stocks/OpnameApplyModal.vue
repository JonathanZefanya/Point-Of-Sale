<script setup>
import { computed } from 'vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import { formatNumber } from '@/lib/format'

/** Tinjau sebelum menerapkan opname: ringkasan selisih. Tidak bisa dibatalkan setelah diterapkan. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  items: { type: Array, required: true }, // [{ product_name, system_quantity, counted }]
  busy: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm'])

const counted = computed(() => props.items.filter((i) => i.counted !== null && i.counted !== undefined))
const changed = computed(() => counted.value.map((i) => ({ ...i, diff: i.counted - i.system_quantity })).filter((i) => i.diff !== 0))
const plus = computed(() => changed.value.filter((i) => i.diff > 0).reduce((s, i) => s + i.diff, 0))
const minus = computed(() => changed.value.filter((i) => i.diff < 0).reduce((s, i) => s + i.diff, 0))
</script>

<template>
  <Modal v-model:open="open" title="Terapkan stock opname?" size="md" :dismissible="!busy">
    <div class="flex flex-col gap-3">
      <dl class="grid grid-cols-3 gap-2 text-center">
        <div class="rounded border border-border p-2"><dt class="text-muted">Dihitung</dt><dd class="text-xl font-semibold tabular-nums">{{ formatNumber(counted.length) }} / {{ formatNumber(items.length) }}</dd></div>
        <div class="rounded border border-border p-2"><dt class="text-muted">Lebih</dt><dd class="text-xl font-semibold text-success tabular-nums">+{{ formatNumber(plus) }}</dd></div>
        <div class="rounded border border-border p-2"><dt class="text-muted">Kurang</dt><dd class="text-xl font-semibold text-danger tabular-nums">{{ formatNumber(minus) }}</dd></div>
      </dl>
      <ul v-if="changed.length" class="max-h-56 divide-y divide-border overflow-y-auto rounded border border-border">
        <li v-for="i in changed" :key="i.id" class="flex justify-between gap-2 px-3 py-1.5">
          <span class="truncate">{{ i.product_name }}</span>
          <span :class="['shrink-0 tabular-nums', i.diff > 0 ? 'text-success' : 'text-danger']">
            {{ formatNumber(i.system_quantity) }} → {{ formatNumber(i.counted) }}
          </span>
        </li>
      </ul>
      <p v-else class="text-muted">Semua jumlah fisik sama dengan stok sistem. Opname tetap dicatat.</p>
      <p>Stok produk yang dihitung diubah menjadi jumlah fisik dan tercatat di kartu stok. Produk yang tidak diisi tidak diubah. Langkah ini tidak bisa dibatalkan.</p>
    </div>
    <template #footer>
      <Button :disabled="busy" @click="open = false">Kembali</Button>
      <Button variant="primary" :loading="busy" loading-text="Menerapkan..." @click="emit('confirm')">Terapkan Opname</Button>
    </template>
  </Modal>
</template>
