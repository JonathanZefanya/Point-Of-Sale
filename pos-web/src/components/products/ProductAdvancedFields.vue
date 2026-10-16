<script setup>
import { computed, ref } from 'vue'
import Checkbox from '@/components/ui/Checkbox.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import { useOutletStore } from '@/stores/outlet'
import ModifierGroupPicker from './ModifierGroupPicker.vue'
import OutletPricesEditor from './OutletPricesEditor.vue'
import VariantsEditor from './VariantsEditor.vue'

/**
 * "Pengaturan lanjutan" (F7.2): jenis, satuan, stok minimum, varian, modifier, harga per outlet.
 * Tertutup secara default agar tidak mengganggu user yang hanya butuh produk sederhana;
 * terbuka otomatis bila sudah dipakai atau ada error di dalamnya.
 */
const form = defineModel({ type: Object, required: true })

const props = defineProps({
  errors: { type: Object, default: () => ({}) },
  modifierGroups: { type: Array, default: () => [] },
  isEdit: { type: Boolean, default: false },
})

const typeOptions = [
  { value: 'goods', label: 'Barang (punya stok)' },
  { value: 'service', label: 'Jasa (tanpa stok)' },
]

const outletStore = useOutletStore()
const hasVariants = computed(() => form.value.variants.some((v) => v.is_active !== false))

const used = Object.keys(form.value.outlet_prices ?? {}).length > 0 || form.value.type !== 'goods' || form.value.unit !== 'pcs' || !form.value.track_stock
  || form.value.variants.length > 0 || form.value.modifier_group_ids.length > 0
const open = ref(used)
const hasError = computed(() => Object.keys(props.errors).some((k) => props.errors[k] && /^(type|unit|track_stock|min_stock|variants|modifier_group_ids|outlet_prices)/.test(k)))
</script>

<template>
  <details class="rounded border border-border" :open="open || hasError" @toggle="open = $event.target.open">
    <summary class="cursor-pointer select-none px-4 py-3 font-medium hover:bg-surface-muted">
      Pengaturan lanjutan
      <span class="font-normal text-muted">· jenis, satuan, stok minimum, varian, modifier{{ outletStore.hasMany ? ', harga per outlet' : '' }}</span>
    </summary>

    <div class="grid gap-6 border-t border-border p-4">
      <div class="grid gap-4 sm:grid-cols-2">
        <Select v-model="form.type" label="Jenis produk" :options="typeOptions" :error="errors.type" />
        <Input v-model="form.unit" label="Satuan" placeholder="pcs, box, botol, porsi" :error="errors.unit" />
        <template v-if="form.type === 'goods'">
          <Checkbox v-model="form.track_stock" label="Lacak stok" description="Stok berkurang setiap terjual. Matikan untuk produk yang selalu tersedia." />
          <Input
            v-model.number="form.min_stock"
            label="Stok minimum"
            type="number"
            min="0"
            inputmode="numeric"
            :disabled="!form.track_stock"
            hint="Muncul peringatan stok menipis jika stok sampai angka ini."
            :error="errors.min_stock"
          />
        </template>
        <p v-else class="text-muted sm:col-span-2">Jasa tidak memiliki stok.</p>
      </div>

      <section class="grid gap-2">
        <h3 class="font-semibold">Varian</h3>
        <VariantsEditor v-model="form.variants" :errors="errors" :show-stock="form.type === 'goods' && form.track_stock" />
      </section>

      <section class="grid gap-2">
        <h3 class="font-semibold">Modifier / add-on</h3>
        <ModifierGroupPicker v-model="form.modifier_group_ids" :groups="modifierGroups" />
      </section>

      <section v-if="outletStore.hasMany" class="grid gap-2">
        <h3 class="font-semibold">Harga & ketersediaan per outlet</h3>
        <OutletPricesEditor v-model="form.outlet_prices" :outlets="outletStore.outlets" :default-price="form.sell_price" :has-variants="hasVariants" />
      </section>
    </div>
  </details>
</template>
