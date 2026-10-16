<script setup>
import { ref, watch } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Modal from '@/components/ui/Modal.vue'
import MoneyInput from '@/components/ui/MoneyInput.vue'
import { useProductApi } from '@/composables/useProductApi'
import { useToastStore } from '@/stores/toast'

/** Tambah/ubah grup modifier: nama, minimal & maksimal pilihan, daftar pilihan + tambahan harga. */
const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  group: { type: Object, default: null }, // null = grup baru
})

const emit = defineEmits(['saved', 'delete'])

const api = useProductApi()
const toast = useToastStore()
const form = ref(null)
const errors = ref({})
const saving = ref(false)

watch(open, (isOpen) => {
  if (!isOpen) return
  errors.value = {}
  form.value = props.group
    ? structuredClone({ ...props.group, modifiers: props.group.modifiers.map((m) => ({ ...m })) })
    : { name: '', min_select: 0, max_select: 1, modifiers: [{ name: '', price: null }] }
})

async function save() {
  saving.value = true
  errors.value = {}
  try {
    const saved = await api.saveModifierGroup({
      ...form.value,
      modifiers: form.value.modifiers.map((m) => ({ id: m.id ?? null, name: m.name, price: m.price ?? 0 })),
    })
    toast.success(`Grup ${saved.name} disimpan.`)
    open.value = false
    emit('saved')
  } catch (e) {
    if (e.fieldErrors) errors.value = e.fieldErrors
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Modal v-model:open="open" :title="group ? `Ubah grup ${group.name}` : 'Tambah grup modifier'" size="lg" :dismissible="!saving">
    <form v-if="form" id="modifier-group-form" class="grid gap-4" novalidate @submit.prevent="save">
      <div class="grid gap-4 sm:grid-cols-3">
        <Input v-model="form.name" label="Nama grup" required placeholder="mis. Level Gula" :error="errors.name" />
        <Input v-model.number="form.min_select" label="Minimal dipilih" type="number" min="0" hint="0 = opsional, 1 = wajib pilih" :error="errors.min_select" />
        <Input v-model.number="form.max_select" label="Maksimal dipilih" type="number" min="1" :error="errors.max_select" />
      </div>

      <fieldset class="grid gap-2">
        <legend class="mb-1 font-medium">Pilihan</legend>
        <p v-if="errors.modifiers" class="text-danger" role="alert">{{ errors.modifiers }}</p>
        <div v-for="(m, i) in form.modifiers" :key="m.id ?? `new-${i}`" class="grid grid-cols-[1fr_180px_auto] items-start gap-2">
          <Input v-model="m.name" :aria-label="`Nama pilihan ${i + 1}`" placeholder="mis. Less sugar" :error="errors[`modifiers.${i}.name`]" />
          <MoneyInput v-model="m.price" :aria-label="`Tambahan harga pilihan ${i + 1}`" placeholder="0" :error="errors[`modifiers.${i}.price`]" />
          <Button variant="danger-ghost" :disabled="form.modifiers.length === 1" @click="form.modifiers.splice(i, 1)">Hapus</Button>
        </div>
        <Button class="justify-self-start" @click="form.modifiers.push({ name: '', price: null })">+ Tambah Pilihan</Button>
      </fieldset>
    </form>
    <template #footer>
      <Button v-if="group" variant="danger-ghost" class="mr-auto" :disabled="saving" @click="emit('delete', group)">Hapus Grup</Button>
      <Button :disabled="saving" @click="open = false">Batal</Button>
      <Button type="submit" form="modifier-group-form" variant="primary" :loading="saving" loading-text="Menyimpan...">Simpan Grup</Button>
    </template>
  </Modal>
</template>
