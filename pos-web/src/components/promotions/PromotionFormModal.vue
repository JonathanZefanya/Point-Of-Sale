<script setup>
import { computed, ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import Select from '@/components/ui/Select.vue'
import { usePromotionApi } from '@/composables/usePromotionApi'
import { PROMOTION_TYPES, isPercentType } from '@/lib/promotions'
import { useToastStore } from '@/stores/toast'
import PromotionScheduleFields from './PromotionScheduleFields.vue'
import PromotionTargetFields from './PromotionTargetFields.vue'

/** Tambah/ubah promo (2.5): jenis & nilai, target, waktu berlaku, voucher. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  promotion: { type: Object, default: null },
  products: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  outlets: { type: Array, default: () => [] },
})

const emit = defineEmits(['saved', 'delete'])

const api = usePromotionApi()
const toast = useToastStore()
const form = ref(null)
const errors = ref({})
const saving = ref(false)

const typeHint = computed(() => PROMOTION_TYPES.find((t) => t.value === form.value?.type)?.hint ?? '')

function blankRules(r = {}) {
  return {
    product_ids: r.product_ids ?? [], category_ids: r.category_ids ?? [], min_purchase: r.min_purchase ?? 0, max_discount: r.max_discount ?? 0,
    buy_product_id: r.buy_product_id ?? null, buy_quantity: r.buy_quantity ?? 1, get_product_id: r.get_product_id ?? null, get_quantity: r.get_quantity ?? 1,
    days: r.days ?? [], start_time: r.start_time ?? '', end_time: r.end_time ?? '',
  }
}

watch(open, (isOpen) => {
  if (!isOpen) return
  errors.value = {}
  const p = props.promotion
  form.value = {
    id: p?.id, name: p?.name ?? '', type: p?.type ?? 'item_percent', value: p?.value ?? 0, rules: blankRules(p?.rules),
    starts_at: p?.starts_at?.slice(0, 10) ?? '', ends_at: p?.ends_at?.slice(0, 10) ?? '', outlet_ids: p?.outlet_ids ?? [],
    requires_voucher: p?.requires_voucher ?? false, voucher_code: p?.voucher_code ?? '', voucher_quota: p?.voucher_quota ?? null, is_active: p?.is_active ?? true,
  }
})

/** Hanya aturan yang relevan untuk jenis promo ini yang dikirim; nilai kosong/0 dibuang. */
function payload() {
  const f = form.value
  const r = f.rules
  const keep = { item_percent: ['product_ids', 'category_ids'], item_amount: ['product_ids', 'category_ids'], order_percent: ['min_purchase', 'max_discount'],
    order_amount: ['min_purchase'], buy_x_get_y: ['buy_product_id', 'buy_quantity', 'get_product_id', 'get_quantity'] }[f.type]
  const rules = Object.fromEntries([...keep, 'days', 'start_time', 'end_time'].map((k) => [k, r[k]]).filter(([, v]) => v !== '' && v !== 0 && v !== null && !(Array.isArray(v) && !v.length)))
  return {
    ...f, rules,
    value: f.type === 'buy_x_get_y' ? null : f.value,
    starts_at: f.starts_at ? `${f.starts_at} 00:00:00` : null,
    ends_at: f.ends_at ? `${f.ends_at} 23:59:59` : null,
    outlet_ids: f.outlet_ids.length ? f.outlet_ids : null,
    voucher_code: f.voucher_code || null,
    voucher_quota: f.voucher_code && f.voucher_quota ? Number(f.voucher_quota) : null,
  }
}

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const saved = await api.save(payload())
    toast.success(`Promo ${saved.name} disimpan.`)
    open.value = false
    emit('saved', saved)
  } catch (e) {
    if (e.fieldErrors) errors.value = e.fieldErrors
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" :title="promotion ? `Ubah ${promotion.name}` : 'Tambah promo'" size="lg" :dismissible="!saving">
    <form v-if="form" id="promotion-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
      <Input v-model="form.name" label="Nama promo" required maxlength="100" placeholder="mis. Happy Hour Kopi" :error="errors.name" class="sm:col-span-2" />
      <Select v-model="form.type" label="Jenis promo" :options="PROMOTION_TYPES" :hint="typeHint" required :error="errors.type" />
      <template v-if="form.type !== 'buy_x_get_y'">
        <Input v-if="isPercentType(form.type)" v-model.number="form.value" type="number" min="1" max="100" label="Diskon (%)" required :error="errors.value" />
        <MoneyInput v-else v-model="form.value" label="Potongan" required :error="errors.value" />
      </template>

      <PromotionTargetFields v-model:rules="form.rules" :type="form.type" :products="products" :categories="categories" :errors="errors" />
      <PromotionScheduleFields v-model:form="form" :outlets="outlets" :errors="errors" />

      <fieldset class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
        <legend class="mb-2 font-semibold">Voucher</legend>
        <Input v-model="form.voucher_code" label="Kode voucher" class="font-mono uppercase" maxlength="40" placeholder="mis. HEMAT10" hint="Kosongkan bila promo berjalan otomatis tanpa kode." :error="errors.voucher_code" />
        <Input v-model.number="form.voucher_quota" type="number" min="1" label="Kuota pemakaian" hint="Kosongkan = tanpa batas." :disabled="!form.voucher_code" :error="errors.voucher_quota" />
        <Checkbox v-model="form.requires_voucher" label="Hanya berlaku dengan kode voucher" description="Bila tidak dicentang, promo juga berjalan otomatis di kasir." class="sm:col-span-2" />
      </fieldset>
      <Checkbox v-model="form.is_active" label="Promo aktif" description="Matikan untuk menghentikan sementara tanpa menghapus." class="sm:col-span-2" />
    </form>
    <template #footer>
      <Button v-if="promotion" variant="danger-ghost" class="mr-auto" :disabled="saving" @click="emit('delete', promotion)">Hapus Promo</Button>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="promotion-form" variant="primary" :loading="saving" loading-text="Menyimpan...">Simpan Promo</Button>
    </template>
  </Modal>
</template>
