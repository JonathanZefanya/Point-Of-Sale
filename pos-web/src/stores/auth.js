import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import http from '@/lib/http'
import { readStorage, writeStorage } from '@/lib/storage'
import { useOutletStore } from '@/stores/outlet'
import { useShiftStore } from '@/stores/shift'

const TOKEN_KEY = 'pos.token'
const CHANNEL_KEY = 'pos.channel' // 'backoffice' | 'cashier' — menentukan halaman login tujuan

export const useAuthStore = defineStore('auth', () => {
  const token = ref(readStorage(TOKEN_KEY))
  const channel = ref(readStorage(CHANNEL_KEY) ?? 'backoffice')
  const user = ref(null)

  const isAuthenticated = computed(() => !!token.value)
  const role = computed(() => user.value?.role ?? null)
  const loginRouteName = computed(() => (channel.value === 'cashier' ? 'cashier-login' : 'login'))

  function hasRole(...roles) {
    return roles.includes(role.value)
  }

  function setSession(newToken, newUser, newChannel = channel.value) {
    token.value = newToken
    user.value = newUser
    channel.value = newChannel
    writeStorage(TOKEN_KEY, newToken)
    writeStorage(CHANNEL_KEY, newChannel)
  }

  /** Back office: email + password. Error ditangani di form (silent). */
  async function login(email, password) {
    const { data } = await http.post('/auth/login', { email, password }, { skipAuthRedirect: true, silent: true })
    setSession(data.data.token, data.data.user, 'backoffice')
    return data.data.user
  }

  /** Perangkat kasir: pilih user + PIN 6 digit. Error ditangani di keypad (silent). */
  async function pinLogin(userId, pin) {
    const { data } = await http.post('/auth/pin-login', { user_id: userId, pin }, { skipAuthRedirect: true, silent: true })
    setSession(data.data.token, data.data.user, 'cashier')
    return data.data.user
  }

  /** Memuat user dari token tersimpan (saat refresh halaman). Dipanggil sekali oleh route guard. */
  let pendingMe = null
  function ensureUser() {
    if (!token.value || user.value) return Promise.resolve(user.value)
    pendingMe ??= http
      .get('/auth/me', { silent: true, skipAuthRedirect: true })
      .then(({ data }) => (user.value = data.data))
      .finally(() => (pendingMe = null))
    return pendingMe
  }

  async function logout() {
    try {
      if (token.value) await http.post('/auth/logout', null, { silent: true, skipAuthRedirect: true })
    } catch {
      // token mungkin sudah tidak berlaku; tetap bersihkan sesi lokal
    } finally {
      clearSession()
    }
  }

  /** Channel dipertahankan agar setelah logout user kembali ke halaman login yang sama. */
  function clearSession() {
    setSession(null, null)
    // data per user tidak boleh terbawa ke user berikutnya
    useOutletStore().reset()
    useShiftStore().reset()
  }

  return {
    token,
    channel,
    user,
    role,
    isAuthenticated,
    loginRouteName,
    hasRole,
    login,
    pinLogin,
    ensureUser,
    logout,
    clearSession,
  }
})
