import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useShiftStore } from '@/stores/shift'
import { BACKOFFICE_ROLES, CASHIER_ROLES, ROLE, STOCK_ROLES } from '@/lib/roles'
import { useToastStore } from '@/stores/toast'

/**
 * meta.title  → judul header & tab browser
 * meta.guest  → hanya untuk yang belum login (halaman login)
 * meta.public → bisa dibuka tanpa login
 * meta.roles  → role yang boleh membuka; selain itu diarahkan ke halaman yang sesuai
 * Selain itu wajib login. Guard status shift (kasir tanpa shift → Buka Shift): FE-1.5.
 */
const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: { title: 'Masuk', guest: true },
  },
  {
    path: '/struk/:id(\\d+)',
    name: 'public-receipt',
    component: () => import('@/views/PublicReceiptView.vue'),
    meta: { title: 'Struk', public: true },
  },
  {
    path: '/kasir/login',
    name: 'cashier-login',
    component: () => import('@/views/cashier/CashierLoginView.vue'),
    meta: { title: 'Masuk Kasir', guest: true },
  },
  {
    path: '/',
    name: 'home',
    component: () => import('@/views/backoffice/DashboardView.vue'),
    meta: { title: 'Dashboard', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/karyawan',
    name: 'users',
    component: () => import('@/views/backoffice/users/UsersView.vue'),
    meta: { title: 'Karyawan', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/karyawan/baru',
    name: 'user-create',
    component: () => import('@/views/backoffice/users/UserFormView.vue'),
    meta: { title: 'Tambah Karyawan', roles: BACKOFFICE_ROLES, nav: 'users' },
  },
  {
    path: '/karyawan/:id(\\d+)',
    name: 'user-edit',
    component: () => import('@/views/backoffice/users/UserFormView.vue'),
    props: true,
    meta: { title: 'Ubah Karyawan', roles: BACKOFFICE_ROLES, nav: 'users' },
  },
  {
    path: '/produk',
    name: 'products',
    component: () => import('@/views/backoffice/products/ProductsView.vue'),
    meta: { title: 'Produk', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/produk/baru',
    name: 'product-create',
    component: () => import('@/views/backoffice/products/ProductFormView.vue'),
    meta: { title: 'Tambah Produk', roles: BACKOFFICE_ROLES, nav: 'products' },
  },
  {
    path: '/produk/:id(\\d+)',
    name: 'product-edit',
    component: () => import('@/views/backoffice/products/ProductFormView.vue'),
    props: true,
    meta: { title: 'Ubah Produk', roles: BACKOFFICE_ROLES, nav: 'products' },
  },
  {
    path: '/produk/modifier',
    name: 'modifier-groups',
    component: () => import('@/views/backoffice/products/ModifierGroupsView.vue'),
    meta: { title: 'Modifier', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/stok',
    name: 'stocks',
    component: () => import('@/views/backoffice/stocks/StocksView.vue'),
    meta: { title: 'Stok', roles: STOCK_ROLES },
  },
  {
    path: '/produk/label',
    name: 'barcode-labels',
    component: () => import('@/views/backoffice/products/BarcodeLabelsView.vue'),
    meta: { title: 'Cetak Label Barcode', roles: STOCK_ROLES },
  },
  {
    path: '/stok/opname',
    name: 'stock-opnames',
    component: () => import('@/views/backoffice/stocks/StockOpnamesView.vue'),
    meta: { title: 'Stock Opname', roles: STOCK_ROLES },
  },
  {
    path: '/stok/opname/:id(\\d+)',
    name: 'stock-opname',
    component: () => import('@/views/backoffice/stocks/StockOpnameView.vue'),
    props: true,
    meta: { title: 'Stock Opname', roles: STOCK_ROLES, nav: 'stock-opnames' },
  },
  {
    path: '/stok/transfer',
    name: 'stock-transfers',
    component: () => import('@/views/backoffice/stocks/StockTransfersView.vue'),
    meta: { title: 'Transfer Stok', roles: STOCK_ROLES },
  },
  {
    path: '/stok/transfer/baru',
    name: 'stock-transfer-new',
    component: () => import('@/views/backoffice/stocks/StockTransferFormView.vue'),
    meta: { title: 'Buat Transfer Stok', roles: STOCK_ROLES, nav: 'stock-transfers' },
  },
  {
    path: '/stok/transfer/:id(\\d+)',
    name: 'stock-transfer',
    component: () => import('@/views/backoffice/stocks/StockTransferDetailView.vue'),
    props: true,
    meta: { title: 'Transfer Stok', roles: STOCK_ROLES, nav: 'stock-transfers' },
  },
  {
    path: '/stok/:productId(\\d+)/:variantId(\\d+)?',
    name: 'stock-card',
    component: () => import('@/views/backoffice/stocks/StockCardView.vue'),
    props: true,
    meta: { title: 'Kartu Stok', roles: STOCK_ROLES, nav: 'stocks' },
  },
  {
    path: '/pembelian',
    name: 'purchase-orders',
    component: () => import('@/views/backoffice/purchases/PurchaseOrdersView.vue'),
    meta: { title: 'Pembelian', roles: STOCK_ROLES },
  },
  {
    path: '/pembelian/baru',
    name: 'purchase-order-new',
    component: () => import('@/views/backoffice/purchases/PurchaseOrderFormView.vue'),
    meta: { title: 'Buat PO', roles: STOCK_ROLES, nav: 'purchase-orders' },
  },
  {
    path: '/pembelian/:id(\\d+)',
    name: 'purchase-order',
    component: () => import('@/views/backoffice/purchases/PurchaseOrderDetailView.vue'),
    props: true,
    meta: { title: 'Detail PO', roles: STOCK_ROLES, nav: 'purchase-orders' },
  },
  {
    path: '/pembelian/:id(\\d+)/ubah',
    name: 'purchase-order-edit',
    component: () => import('@/views/backoffice/purchases/PurchaseOrderFormView.vue'),
    props: true,
    meta: { title: 'Ubah PO', roles: STOCK_ROLES, nav: 'purchase-orders' },
  },
  {
    path: '/supplier',
    name: 'suppliers',
    component: () => import('@/views/backoffice/purchases/SuppliersView.vue'),
    meta: { title: 'Supplier', roles: STOCK_ROLES },
  },
  {
    path: '/outlet',
    name: 'outlets',
    component: () => import('@/views/backoffice/OutletsView.vue'),
    meta: { title: 'Outlet', roles: [ROLE.OWNER] },
  },
  {
    path: '/audit-log',
    name: 'audit-logs',
    component: () => import('@/views/backoffice/AuditLogsView.vue'),
    meta: { title: 'Audit Log', roles: [ROLE.OWNER] },
  },
  {
    path: '/absensi',
    name: 'attendances',
    component: () => import('@/views/backoffice/AttendancesView.vue'),
    meta: { title: 'Absensi', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/meja',
    name: 'tables',
    component: () => import('@/views/backoffice/TablesView.vue'),
    meta: { title: 'Meja', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/pelanggan',
    name: 'customers',
    component: () => import('@/views/backoffice/CustomersView.vue'),
    meta: { title: 'Pelanggan', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/promo',
    name: 'promotions',
    component: () => import('@/views/backoffice/PromotionsView.vue'),
    meta: { title: 'Promo & Voucher', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/laporan',
    name: 'reports',
    component: () => import('@/views/backoffice/ReportsView.vue'),
    meta: { title: 'Laporan', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/pengaturan',
    name: 'settings',
    component: () => import('@/views/backoffice/SettingsView.vue'),
    meta: { title: 'Pengaturan', roles: BACKOFFICE_ROLES },
  },
  {
    path: '/tidak-ada-akses',
    name: 'forbidden',
    component: () => import('@/views/ForbiddenView.vue'),
    meta: { title: 'Tidak ada akses' },
  },
  {
    path: '/dapur',
    name: 'kitchen',
    component: () => import('@/views/cashier/KitchenView.vue'),
    meta: { title: 'Dapur', roles: CASHIER_ROLES },
  },
  {
    path: '/kasir',
    name: 'cashier',
    component: () => import('@/views/cashier/CashierView.vue'),
    meta: { title: 'Kasir', roles: CASHIER_ROLES, requiresShift: true },
  },
  {
    path: '/kasir/riwayat',
    name: 'cashier-history',
    component: () => import('@/views/cashier/CashierHistoryView.vue'),
    meta: { title: 'Riwayat Transaksi', roles: CASHIER_ROLES, requiresShift: true },
  },
  {
    path: '/kasir/buka-shift',
    name: 'shift-open',
    component: () => import('@/views/cashier/ShiftOpenView.vue'),
    meta: { title: 'Buka Shift', roles: CASHIER_ROLES },
  },
  {
    path: '/kasir/tutup-shift',
    name: 'shift-close',
    component: () => import('@/views/cashier/ShiftCloseView.vue'),
    meta: { title: 'Tutup Shift', roles: CASHIER_ROLES },
  },
]

// Pratinjau komponen ui/ dan layout — hanya ada di mode development
if (import.meta.env.DEV) {
  routes.push(
    {
      path: '/_ui',
      name: 'dev-ui',
      component: () => import('@/views/dev/UiPreviewView.vue'),
      meta: { title: 'Pratinjau Komponen', public: true },
    },
    {
      path: '/_ui/kasir',
      name: 'dev-cashier',
      component: () => import('@/views/dev/CashierPreviewView.vue'),
      meta: { title: 'Pratinjau Layar Kasir', public: true },
    },
  )
}

routes.push({
  path: '/:pathMatch(.*)*',
  name: 'not-found',
  component: () => import('@/views/NotFoundView.vue'),
  meta: { title: 'Halaman tidak ditemukan', public: true },
})

const router = createRouter({
  history: createWebHistory(),
  routes,
})

function homeFor(auth) {
  if (auth.channel === 'cashier') return { name: 'cashier' }
  // Staf Gudang tidak punya dashboard; halaman kerjanya adalah Stok
  return { name: auth.role === ROLE.WAREHOUSE ? 'stocks' : 'home' }
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) return true

  if (to.meta.guest) {
    return auth.isAuthenticated ? homeFor(auth) : true
  }

  if (!auth.isAuthenticated) {
    const query = auth.loginRouteName === 'login' && to.fullPath !== '/' ? { redirect: to.fullPath } : {}
    return { name: auth.loginRouteName, query }
  }

  try {
    await auth.ensureUser()
  } catch (error) {
    if (error.status === 401) {
      auth.clearSession()
      return { name: auth.loginRouteName }
    }
    // Server tidak terjangkau: tetap buka halaman, data user dimuat ulang saat koneksi kembali
    useToastStore().error(error.userMessage)
    return true
  }

  const roleResult = checkRole(to, auth)
  if (roleResult !== true) return roleResult

  return checkShift(to)
})

/** Kasir tanpa shift terbuka → "Buka Shift"; yang sudah buka shift tidak perlu ke sana lagi (F2.1). */
async function checkShift(to) {
  if (!to.meta.requiresShift && to.name !== 'shift-open') return true

  const shiftStore = useShiftStore()
  try {
    await shiftStore.ensureLoaded()
  } catch {
    return true // gagal memuat: halaman tujuan menampilkan errornya sendiri
  }

  if (to.meta.requiresShift && !shiftStore.isOpen) return { name: 'shift-open' }
  if (to.name === 'shift-open' && shiftStore.isOpen) return { name: 'cashier' }
  return true
}

/** Arahkan user ke halaman yang memang boleh ia buka, bukan sekadar menolak. */
function checkRole(to, auth) {
  const allowed = to.meta.roles
  if (!allowed || allowed.includes(auth.role)) return true

  if (auth.role === ROLE.CASHIER) {
    useToastStore().info('Back office hanya untuk pemilik dan manajer. Anda diarahkan ke layar kasir.')
    return { name: 'cashier' }
  }

  if (auth.role === ROLE.WAREHOUSE && to.name !== 'stocks') return { name: 'stocks' }

  return to.name === 'forbidden' ? true : { name: 'forbidden' }
}

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · POS` : 'POS'
})

export default router
