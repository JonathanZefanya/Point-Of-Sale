<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Modal from '@/components/ui/Modal.vue'
import { formatNumber, formatRupiah } from '@/lib/format'

/**
 * Pop-up pilihan varian & modifier (F3.2, 2.1). Satu tap per pilihan; bila produk hanya punya
 * varian tanpa modifier, tap varian langsung menambah ke keranjang dan pop-up tertutup sendiri.
 */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  product: { type: Object, default: null },
  initialVariantId: { type: Number, default: null }, // dari scan barcode varian
})

const emit = defineEmits(['add'])

const variantId = ref(null)
const selected = ref({}) // group_id → [modifier_id]
const error = ref('')

const variants = computed(() => (props.product?.variants ?? []).filter((v) => v.is_active))
const groups = computed(() => props.product?.modifier_groups ?? [])
const variant = computed(() => variants.value.find((v) => v.id === variantId.value) ?? null)
const chosen = computed(() => groups.value.flatMap((g) => g.modifiers.filter((m) => (selected.value[g.id] ?? []).includes(m.id))))
const price = computed(() => (variant.value?.sell_price ?? props.product?.sell_price ?? 0) + chosen.value.reduce((s, m) => s + m.price, 0))

watch(open, (isOpen) => {
  if (!isOpen) return
  variantId.value = props.initialVariantId
  selected.value = {}
  error.value = ''
})

function pickVariant(v) {
  variantId.value = v.id
  if (!groups.value.length) submit() // hanya varian → langsung tambah
}

function toggle(group, modifier) {
  const current = selected.value[group.id] ?? []
  if (group.max_select === 1) {
    selected.value = { ...selected.value, [group.id]: current.includes(modifier.id) && group.min_select === 0 ? [] : [modifier.id] }
  } else if (current.includes(modifier.id)) {
    selected.value = { ...selected.value, [group.id]: current.filter((id) => id !== modifier.id) }
  } else if (current.length < group.max_select) {
    selected.value = { ...selected.value, [group.id]: [...current, modifier.id] }
  }
  error.value = ''
}

function submit() {
  if (variants.value.length && !variant.value) return (error.value = 'Pilih varian terlebih dahulu.')
  const missing = groups.value.find((g) => (selected.value[g.id]?.length ?? 0) < g.min_select)
  if (missing) return (error.value = `Pilih ${missing.name} (minimal ${missing.min_select}).`)
  emit('add', { variant: variant.value, modifiers: chosen.value.map(({ id, name, price: p }) => ({ id, name, price: p })) })
  open.value = false
}

const optionClass = (active) => [
  'min-h-touch rounded border px-3 py-2 text-left',
  active ? 'border-primary bg-primary-soft font-medium text-primary' : 'border-border-strong bg-surface hover:bg-surface-muted',
]
</script>

<template>
  <Modal v-model:open="open" :title="product?.name ?? ''" size="md">
    <div v-if="product" class="cashier-root grid gap-4">
      <section v-if="variants.length">
        <p class="mb-2 font-medium">Pilih varian</p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
          <button v-for="v in variants" :key="v.id" type="button" :class="optionClass(variantId === v.id)" @click="pickVariant(v)">
            <span class="block">{{ v.name }}</span>
            <span class="block text-sm tabular-nums text-muted">{{ formatNumber(v.sell_price) }}</span>
          </button>
        </div>
      </section>

      <section v-for="g in groups" :key="g.id">
        <p class="mb-2 font-medium">
          {{ g.name }}
          <span class="font-normal text-muted">· {{ g.min_select ? `wajib pilih ${g.min_select}` : 'opsional' }}{{ g.max_select > 1 ? `, maks ${g.max_select}` : '' }}</span>
        </p>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
          <button v-for="m in g.modifiers" :key="m.id" type="button" :class="optionClass((selected[g.id] ?? []).includes(m.id))" @click="toggle(g, m)">
            <span class="block">{{ m.name }}</span>
            <span v-if="m.price" class="block text-sm tabular-nums text-muted">+{{ formatNumber(m.price) }}</span>
          </button>
        </div>
      </section>

      <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
    </div>
    <template v-if="groups.length || !variants.length" #footer>
      <Button size="lg" @click="open = false">Batal</Button>
      <Button size="lg" variant="primary" @click="submit">Tambah · {{ formatRupiah(price) }}</Button>
    </template>
  </Modal>
</template>
