<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import OutletSelect from '@/components/outlets/OutletSelect.vue'
import Button from '@/components/ui/Button.vue'
import { useAuthStore } from '@/stores/auth'
import { useOutletStore } from '@/stores/outlet'

const props = defineProps({
  title: { type: String, default: '' }, // judul halaman; default dari route.meta.title
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const outletStore = useOutletStore()

// Switch outlet di header (2.8): semua halaman back office memakai outlet terpilih
onMounted(() => {
  if (auth.isAuthenticated) outletStore.ensureLoaded().catch(() => {})
})

// Menu ditulis lengkap sesuai rencana; item muncul otomatis begitu route-nya didaftarkan
// dan hanya jika role user boleh membukanya (route.meta.roles).
const sections = [
  {
    label: 'Utama',
    items: [
      { name: 'home', label: 'Dashboard' },
      { name: 'cashier', label: 'Buka Layar Kasir' },
    ],
  },
  {
    label: 'Produk & Stok',
    items: [
      { name: 'products', label: 'Produk' },
      { name: 'categories', label: 'Kategori' },
      { name: 'modifier-groups', label: 'Modifier' },
      { name: 'barcode-labels', label: 'Cetak Label' },
      { name: 'stocks', label: 'Stok' },
      { name: 'stock-opnames', label: 'Stock Opname' },
      { name: 'stock-transfers', label: 'Transfer Stok' },
      { name: 'purchase-orders', label: 'Pembelian' },
      { name: 'suppliers', label: 'Supplier' },
    ],
  },
  {
    label: 'Penjualan',
    items: [
      { name: 'sales', label: 'Riwayat Transaksi' },
      { name: 'customers', label: 'Pelanggan' },
      { name: 'tables', label: 'Meja' },
      { name: 'promotions', label: 'Promo' },
      { name: 'shifts', label: 'Shift' },
      { name: 'reports', label: 'Laporan' },
    ],
  },
  {
    label: 'Pengaturan',
    items: [
      { name: 'users', label: 'Karyawan' },
      { name: 'attendances', label: 'Absensi' },
      { name: 'outlets', label: 'Outlet' },
      { name: 'settings', label: 'Pengaturan' },
      { name: 'audit-logs', label: 'Audit Log' },
    ],
  },
]

function canOpen(item) {
  if (!router.hasRoute(item.name)) return false
  const roles = router.resolve({ name: item.name }).meta.roles
  return !roles || roles.includes(auth.role)
}

/** Sub-halaman (mis. Tambah Karyawan) menandai menu induknya lewat meta.nav */
function isActive(item) {
  return route.name === item.name || route.meta.nav === item.name
}

const visibleSections = computed(() =>
  sections
    .map((s) => ({ ...s, items: s.items.filter(canOpen) }))
    .filter((s) => s.items.length),
)

const pageTitle = computed(() => props.title || route.meta.title || '')
const outletName = computed(() => outletStore.current?.name ?? '')

async function logout() {
  await auth.logout()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-screen">
    <aside class="sticky top-0 flex h-screen w-56 shrink-0 flex-col border-r border-border bg-surface">
      <div class="flex h-14 items-center border-b border-border px-4">
        <span class="font-semibold">POS</span>
        <span v-if="outletName" class="ml-2 truncate text-muted">· {{ outletName }}</span>
      </div>

      <nav class="flex-1 overflow-y-auto px-2 py-3" aria-label="Menu utama">
        <div v-for="section in visibleSections" :key="section.label" class="mb-4">
          <p class="px-2 pb-1 text-xs font-medium uppercase tracking-wide text-muted">{{ section.label }}</p>
          <RouterLink
            v-for="item in section.items"
            :key="item.name"
            :to="{ name: item.name }"
            :class="[
              'block rounded px-2 py-1.5',
              isActive(item) ? 'bg-primary-soft font-medium text-primary' : 'text-text hover:bg-surface-muted',
            ]"
            :aria-current="isActive(item) ? 'page' : undefined"
          >
            {{ item.label }}
          </RouterLink>
        </div>
      </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="sticky top-0 z-10 flex h-14 items-center justify-between gap-4 border-b border-border bg-surface px-6">
        <h1 class="truncate text-base font-semibold">{{ pageTitle }}</h1>
        <div class="flex items-center gap-3">
          <OutletSelect />
          <slot name="header-actions" />
          <span v-if="auth.user" class="text-muted">{{ auth.user.name }}</span>
          <Button v-if="auth.isAuthenticated" variant="ghost" @click="logout">Keluar</Button>
        </div>
      </header>

      <main class="flex-1 px-6 py-5">
        <slot />
      </main>
    </div>
  </div>
</template>
