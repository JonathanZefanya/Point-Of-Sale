<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({ email: '', password: '' })
const errors = reactive({ email: '', password: '' })
const formError = ref('')
const showPassword = ref(false)
const submitting = ref(false)

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

function validateEmail() {
  errors.email = form.email && !EMAIL_PATTERN.test(form.email) ? 'Format email salah, contoh: nama@toko.com' : ''
}

function validate() {
  errors.email = form.email ? errors.email : 'Email wajib diisi.'
  errors.password = form.password ? '' : 'Password wajib diisi.'
  if (form.email) validateEmail()
  return !errors.email && !errors.password
}

async function submit() {
  formError.value = ''
  if (!validate()) return

  submitting.value = true
  try {
    await auth.login(form.email.trim(), form.password)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null
    router.replace(redirect ?? { name: 'home' })
  } catch (error) {
    if (error.fieldErrors) {
      errors.email = error.fieldErrors.email ?? ''
      errors.password = error.fieldErrors.password ?? ''
    } else {
      formError.value = error.userMessage
    }
    form.password = ''
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-start justify-center px-4 pt-[12vh]">
    <div class="w-full max-w-sm">
      <p class="mb-1 text-muted">POS · Back office</p>
      <h1 class="mb-5 text-xl font-semibold">Masuk ke akun Anda</h1>

      <form class="flex flex-col gap-4 rounded border border-border bg-surface p-5" novalidate @submit.prevent="submit">
        <Input
          v-model="form.email"
          label="Email"
          type="email"
          autocomplete="username"
          placeholder="nama@toko.com"
          autofocus
          :error="errors.email"
          @blur="validateEmail"
          @input="errors.email = ''"
        />

        <div class="flex flex-col gap-1.5">
          <Input
            v-model="form.password"
            label="Password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            :error="errors.password"
            @input="errors.password = ''"
          />
          <button
            type="button"
            class="self-start text-muted underline-offset-2 hover:text-text hover:underline"
            :aria-pressed="showPassword"
            @click="showPassword = !showPassword"
          >
            {{ showPassword ? 'Sembunyikan password' : 'Tampilkan password' }}
          </button>
        </div>

        <p v-if="formError" class="rounded border border-danger/30 bg-danger-soft px-3 py-2 text-danger" role="alert">
          {{ formError }}
        </p>

        <Button type="submit" variant="primary" block :loading="submitting" loading-text="Memeriksa...">Masuk</Button>
      </form>

      <p class="mt-4 text-muted">
        Kasir?
        <RouterLink :to="{ name: 'cashier-login' }" class="font-medium text-primary underline-offset-2 hover:underline">
          Masuk dengan PIN di layar kasir
        </RouterLink>
      </p>
    </div>
  </div>
</template>
