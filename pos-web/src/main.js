import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import { configureHttp } from './lib/http'
import router from './router'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'
import './style.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

const auth = useAuthStore()
const toast = useToastStore()

configureHttp({
  getToken: () => auth.token,
  onUnauthorized: () => {
    auth.clearSession()
    toast.error('Sesi Anda telah berakhir. Silakan login kembali.')
    router.push({ name: auth.loginRouteName })
  },
  notifyError: (message) => toast.error(message),
})

app.mount('#app')
