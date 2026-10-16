<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { ROLE_LABEL } from '@/lib/roles'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const roleLabel = computed(() => ROLE_LABEL[auth.role] ?? 'Anda')

async function logout() {
  await auth.logout()
  router.replace({ name: auth.loginRouteName })
}
</script>

<template>
  <div class="mx-auto mt-24 max-w-md rounded border border-border bg-surface">
    <EmptyState
      title="Halaman ini tidak tersedia untuk akun Anda."
      :description="`Akun ${roleLabel} belum punya akses ke halaman ini. Minta pemilik toko mengubah peran Anda, atau masuk dengan akun lain.`"
    >
      <Button variant="primary" @click="logout">Masuk dengan Akun Lain</Button>
    </EmptyState>
  </div>
</template>
