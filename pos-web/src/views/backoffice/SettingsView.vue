<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import BusinessSettingsFields from '@/components/settings/BusinessSettingsFields.vue'
import IntegrationSettingsFields from '@/components/settings/IntegrationSettingsFields.vue'
import NotificationSettingsFields from '@/components/settings/NotificationSettingsFields.vue'
import OutletSettingsFields from '@/components/settings/OutletSettingsFields.vue'
import ReceiptPreview from '@/components/settings/ReceiptPreview.vue'
import ReceiptSettingsFields from '@/components/settings/ReceiptSettingsFields.vue'
import SalesSettingsFields from '@/components/settings/SalesSettingsFields.vue'
import SettingsTabs from '@/components/settings/SettingsTabs.vue'
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Skeleton from '@/components/ui/Skeleton.vue'
import BackofficeLayout from '@/layouts/BackofficeLayout.vue'
import http from '@/lib/http'
import { ROLE } from '@/lib/roles'
import { useAuthStore } from '@/stores/auth'
import { useOutletStore } from '@/stores/outlet'
import { useSettingsStore } from '@/stores/settings'
import { useToastStore } from '@/stores/toast'

/**
 * Pengaturan (FE-1.14), dikelompokkan per tab. Satu tombol Simpan untuk semua tab agar perubahan
 * tidak hilang saat berpindah tab. Manajer hanya melihat tab yang berisi pengaturan outletnya.
 */
const auth = useAuthStore()
const outletStore = useOutletStore()
const settingsStore = useSettingsStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()

const isOwner = computed(() => auth.role === ROLE.OWNER)
const settings = ref(null)
const outlet = ref(null)
const errors = ref({})
const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
// Notifikasi email (3.8): "Kirim Contoh" memakai data tersimpan, jadi dinonaktifkan bila ada perubahan belum disimpan
const notificationsDirty = computed(() => JSON.stringify(settings.value?.notifications) !== JSON.stringify(settingsStore.settings?.notifications))

// Tab → awalan key error dari server (untuk menandai tab yang bermasalah)
const TABS = [
  { value: 'bisnis', label: 'Bisnis', owner: true, keys: ['business.'] },
  { value: 'penjualan', label: 'Penjualan', keys: ['tax.', 'service_charge.', 'rounding.', 'discount.', 'loyalty.', 'kitchen.', 'outlet.tax_rate', 'outlet.service_charge_rate'] },
  { value: 'struk', label: 'Struk', keys: ['receipt.', 'outlet.receipt_'] },
  { value: 'integrasi', label: 'Integrasi', owner: true, keys: ['midtrans.', 'smtp.'] },
  { value: 'notifikasi', label: 'Notifikasi', owner: true, keys: ['notifications.'] },
]
const tabs = computed(() => TABS.filter((t) => isOwner.value || !t.owner).map((t) => ({
  ...t,
  hasError: Object.keys(errors.value).some((k) => t.keys.some((p) => k.startsWith(p))),
})))
const tab = computed({
  get: () => (tabs.value.some((t) => t.value === route.query.tab) ? route.query.tab : tabs.value[0].value),
  set: (value) => router.replace({ query: { ...route.query, tab: value } }),
})

const OUTLET_FIELDS = ['id', 'code', 'name', 'address', 'phone', 'tax_rate', 'service_charge_rate', 'receipt_header', 'receipt_footer']
const copyOutlet = (o) => (o ? Object.fromEntries(OUTLET_FIELDS.map((k) => [k, o[k] ?? (k.startsWith('receipt') ? '' : null)])) : null)

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [data] = await Promise.all([settingsStore.refresh(), outletStore.ensureLoaded()])
    settings.value = structuredClone(data)
    outlet.value = copyOutlet(outletStore.current)
  } catch (e) {
    loadError.value = e.userMessage
  } finally {
    loading.value = false
  }
}

// Ganti outlet di header → form outlet ikut berganti
watch(() => outletStore.current?.id, () => (outlet.value = copyOutlet(outletStore.current)))

async function save() {
  saving.value = true
  errors.value = {}
  const { id, tax_rate: taxRate, service_charge_rate: serviceRate, receipt_header: header, receipt_footer: footer } = outlet.value
  const payload = {
    outlet: { id, tax_rate: Number(taxRate) || 0, service_charge_rate: Number(serviceRate) || 0, receipt_header: header || null, receipt_footer: footer || null },
  }
  if (isOwner.value) {
    const { business, tax, service_charge: service, rounding, receipt, discount, loyalty, kitchen, notifications, midtrans, smtp } = settings.value
    Object.assign(payload, { business, tax, service_charge: service, rounding, receipt, discount, loyalty, kitchen, notifications, midtrans, smtp })
  }

  try {
    const { data } = await http.put('/settings', payload)
    settingsStore.settings = data.data
    settings.value = structuredClone(data.data) // kosongkan kolom rahasia yang baru disimpan
    // Perbarui data outlet di store agar layar kasir langsung memakai tarif baru
    const index = outletStore.outlets.findIndex((o) => o.id === id)
    if (index !== -1) outletStore.outlets[index] = { ...outletStore.outlets[index], ...payload.outlet }
    toast.success('Pengaturan disimpan.')
  } catch (e) {
    if (e.fieldErrors) {
      errors.value = e.fieldErrors
      // Buka tab pertama yang bermasalah bila tab saat ini tidak punya error
      const first = tabs.value.find((t) => t.hasError)
      if (first && !tabs.value.find((t) => t.value === tab.value)?.hasError) tab.value = first.value
      toast.error('Ada isian yang belum benar. Periksa tab bertanda merah.')
    }
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <BackofficeLayout>
    <div v-if="loading" class="grid max-w-5xl gap-4" aria-busy="true"><Skeleton v-for="n in 5" :key="n" height="3rem" /></div>

    <div v-else-if="loadError" class="max-w-xl rounded border border-border bg-surface">
      <EmptyState title="Pengaturan gagal dimuat." :description="loadError"><Button variant="primary" @click="load">Coba Lagi</Button></EmptyState>
    </div>

    <form v-else class="grid max-w-6xl gap-4" novalidate @submit.prevent="save">
      <SettingsTabs v-model="tab" :tabs="tabs" />
      <p v-if="!isOwner" class="rounded border border-border bg-surface-muted px-3 py-2 text-muted">
        Anda bisa mengubah pengaturan outlet. Pengaturan yang berlaku untuk semua outlet hanya bisa diubah pemilik.
      </p>

      <div :id="`settings-panel-${tab}`" role="tabpanel" :aria-labelledby="`settings-tab-${tab}`" :class="['grid gap-6', tab === 'struk' && 'lg:grid-cols-[1fr_auto]']">
        <div class="rounded border border-border bg-surface">
          <div class="grid gap-8 p-5">
            <BusinessSettingsFields v-if="tab === 'bisnis'" v-model="settings.business" :errors="errors" />
            <template v-else-if="tab === 'penjualan'">
              <OutletSettingsFields v-if="outlet" v-model="outlet" part="rates" :errors="errors" :tax-enabled="settings.tax.enabled" :service-enabled="settings.service_charge.enabled" />
              <SalesSettingsFields v-model="settings" :errors="errors" :disabled="!isOwner" />
            </template>
            <template v-else-if="tab === 'struk'">
              <OutletSettingsFields v-if="outlet" v-model="outlet" part="receipt" :errors="errors" />
              <ReceiptSettingsFields v-model="settings.receipt" :disabled="!isOwner" />
            </template>
            <IntegrationSettingsFields v-else-if="tab === 'integrasi'" v-model="settings" :errors="errors" />
            <NotificationSettingsFields v-else-if="tab === 'notifikasi'" v-model="settings.notifications" :errors="errors" :dirty="notificationsDirty" />
          </div>
          <footer class="flex justify-end border-t border-border px-5 py-3">
            <Button type="submit" variant="primary" :loading="saving" loading-text="Menyimpan...">Simpan Pengaturan</Button>
          </footer>
        </div>

        <aside v-if="tab === 'struk' && outlet" class="lg:sticky lg:top-20 lg:self-start">
          <ReceiptPreview :settings="settings" :outlet="outlet" />
        </aside>
      </div>
    </form>
  </BackofficeLayout>
</template>
