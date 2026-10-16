<script setup>
import ConfirmModal from '@/components/ui/ConfirmModal.vue'

const open = defineModel('open', { type: Boolean, default: false })

defineProps({
  name: { type: String, required: true },
  deleting: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm'])
</script>

<template>
  <ConfirmModal
    v-model:open="open"
    :title="`Hapus karyawan ${name}?`"
    confirm-label="Hapus Karyawan"
    busy-label="Menghapus..."
    :busy="deleting"
    @confirm="emit('confirm')"
  >
    <p>{{ name }} tidak akan bisa login lagi dan hilang dari daftar karyawan. Riwayat transaksinya tetap tersimpan.</p>
    <p class="text-muted">Jika hanya cuti, cukup hilangkan centang "Akun aktif" agar bisa diaktifkan kembali nanti.</p>
  </ConfirmModal>
</template>
