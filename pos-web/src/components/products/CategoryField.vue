<script setup>
import { computed, nextTick, ref } from 'vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import { useProductApi } from '@/composables/useProductApi'

/** Pilih kategori, atau buat kategori baru langsung dari form produk (F7). */
const model = defineModel({ type: [Number, null], default: null })

const props = defineProps({
  categories: { type: Array, default: () => [] },
  error: { type: String, default: '' },
})

const emit = defineEmits(['created'])

const api = useProductApi()
const creating = ref(false)
const newName = ref('')
const newError = ref('')
const saving = ref(false)
const nameInput = ref(null)

const options = computed(() => [
  { value: null, label: 'Tanpa kategori' },
  ...props.categories.map((c) => ({ value: c.id, label: c.name })),
])

async function startCreate() {
  creating.value = true
  newName.value = ''
  newError.value = ''
  await nextTick()
  nameInput.value?.focus()
}

async function create() {
  if (!newName.value.trim()) {
    newError.value = 'Nama kategori wajib diisi.'
    return
  }
  saving.value = true
  try {
    const category = await api.createCategory(newName.value.trim())
    emit('created', category)
    model.value = category.id
    creating.value = false
  } catch (error) {
    newError.value = error.fieldErrors?.name ?? error.userMessage
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <!-- mt-[26px]: sejajarkan tombol dengan kotak input (di bawah label) -->
  <div v-if="!creating" class="flex items-start gap-2">
    <div class="min-w-0 flex-1">
      <Select v-model="model" label="Kategori" :options="options" :error="error" />
    </div>
    <Button class="mt-[26px] shrink-0" @click="startCreate">Kategori Baru</Button>
  </div>

  <div v-else class="flex items-start gap-2">
    <div class="min-w-0 flex-1">
      <Input
        ref="nameInput"
        v-model="newName"
        label="Nama kategori baru"
        placeholder="mis. Minuman"
        :error="newError"
        @input="newError = ''"
        @keydown.enter.prevent="create"
        @keydown.esc.prevent="creating = false"
      />
    </div>
    <div class="mt-[26px] flex shrink-0 gap-2">
      <Button variant="primary" :loading="saving" loading-text="Menyimpan..." @click="create">Tambah</Button>
      <Button :disabled="saving" @click="creating = false">Batal</Button>
    </div>
  </div>
</template>
