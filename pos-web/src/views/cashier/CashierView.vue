<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import CartPanel from '@/components/cashier/CartPanel.vue'
import CashierMenu from '@/components/cashier/CashierMenu.vue'
import CustomerPickerModal from '@/components/cashier/CustomerPickerModal.vue'
import DiscountModal from '@/components/cashier/DiscountModal.vue'
import HeldListModal from '@/components/cashier/HeldListModal.vue'
import HoldModal from '@/components/cashier/HoldModal.vue'
import AttendanceModal from '@/components/attendance/AttendanceModal.vue'
import TablesModal from '@/components/cashier/TablesModal.vue'
import WhatsAppShareButton from '@/components/receipt/WhatsAppShareButton.vue'
import ProductOptionsModal from '@/components/cashier/ProductOptionsModal.vue'
import ProductPanel from '@/components/cashier/ProductPanel.vue'
import PaymentModal from '@/components/payment/PaymentModal.vue'
import SaleSuccessModal from '@/components/payment/SaleSuccessModal.vue'
import CashMovementModal from '@/components/shift/CashMovementModal.vue'
import Button from '@/components/ui/Button.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import { useCartQuote } from '@/composables/useCartQuote'
import { useCatalog } from '@/composables/useCatalog'
import { useHeldSales } from '@/composables/useHeldSales'
import { useOnlineStatus } from '@/composables/useOnlineStatus'
import { useTableApi } from '@/composables/useTableApi'
import { usePrint } from '@/composables/usePrint'
import CashierLayout from '@/layouts/CashierLayout.vue'
import { useShortcuts } from '@/lib/shortcuts'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useDeviceStore } from '@/stores/device'
import { useOutletStore } from '@/stores/outlet'
import { useSettingsStore } from '@/stores/settings'
import { useShiftStore } from '@/stores/shift'
import { useToastStore } from '@/stores/toast'

/** Layar kasir (§7.4, F3–F4): kiri pilih produk, kanan keranjang & bayar. */
const auth = useAuthStore()
const cart = useCartStore()
const shiftStore = useShiftStore()
const outletStore = useOutletStore()
const toast = useToastStore()
const router = useRouter()

const outletId = computed(() => shiftStore.outletId)
const catalog = useCatalog(outletId)
const held = useHeldSales(outletId)
const quote = useCartQuote(outletId)

// F&B (3.3): fitur meja hanya tampil bila outlet punya meja aktif
const hasTables = ref(false)
const refreshTables = () => outletId.value && useTableApi().list(outletId.value, true).then((t) => (hasTables.value = cart.fnbMode = t.length > 0)).catch(() => {})

// Offline (FE-3.1): total dihitung lokal dari harga perangkat; katalog bisa berasal dari cache perangkat
const { online } = useOnlineStatus()
watch(online, (isOnline) => {
  if (!isOnline) cart.setQuote(null)
  else if (catalog.cachedAt.value) catalog.load() // ambil harga terbaru setelah memakai cache
})
watch(() => catalog.cachedAt.value, (at) => at && toast.info('Server tidak terjangkau. Produk & harga memakai data terakhir yang tersimpan di perangkat.'))
const productPanel = ref(null)
const { printSale } = usePrint()
const settingsStore = useSettingsStore()

const modals = ref({ attendance: false, tables: false, discount: false, customer: false, options: false, pay: false, success: false, hold: false, held: false, cash: false, discard: false })
const lastSale = ref(null)
const anyModalOpen = computed(() => Object.values(modals.value).some(Boolean))

const outletCode = computed(() => useDeviceStore().outletCode ?? outletStore.current?.code ?? '')
// product_id → jumlah di keranjang (badge di kartu produk)
const quantities = computed(() =>
  cart.items.reduce((map, i) => ({ ...map, [i.product_id]: (map[i.product_id] ?? 0) + i.quantity }), {}),
)

const optionsProduct = ref(null)

/** Produk bervarian/bermodifier → pop-up pilihan (F3.2); selain itu langsung masuk keranjang. */
function addProduct(product) {
  const hasVariants = (product.variants ?? []).some((v) => v.is_active)
  const hasModifiers = (product.modifier_groups ?? []).length > 0
  const matched = product.variants?.find((v) => v.id === product.matched_variant_id)
  if (!hasVariants && !hasModifiers) return cart.add(product)
  if (matched && !hasModifiers) return cart.add(product, 1, { variant: matched })
  optionsProduct.value = product
  modals.value.options = true
}

/** Pastikan total di modal bayar sudah versi server (promo/voucher/diskon) sebelum dibuka. */
async function openPay() {
  if (cart.isEmpty) return
  await quote.flush()
  modals.value.pay = true
}

function openHold() {
  if (!cart.isEmpty) modals.value.hold = true
}

async function saveHold(label) {
  if (await held.hold(label)) modals.value.hold = false
}

function onPaid(sale) {
  lastSale.value = sale
  modals.value.success = true
  // Struk otomatis tercetak bila diaktifkan di pengaturan (F3.8)
  if (!sale.offline && settingsStore.settings?.receipt?.auto_print) printSale(sale.id).catch(() => {})
}

function newTransaction() {
  const wasHeld = !!cart.heldSaleId
  cart.clear()
  if (wasHeld) held.refresh()
  productPanel.value?.focusSearch()
}

function onStockError(data) {
  cart.setStockIssues(data.items)
  toast.error(`${data.message} Kurangi jumlah item yang ditandai merah.`)
}

async function discardHeld() {
  await held.discardCurrent()
  modals.value.discard = false
}

const menuItems = [
  { label: 'Absensi', description: 'Absen masuk atau pulang', onSelect: () => (modals.value.attendance = true) },
  { label: 'Layar Dapur', description: 'Pesanan untuk dapur: baru, diproses, siap', onSelect: () => router.push({ name: 'kitchen' }) },
  { label: 'Riwayat Transaksi', description: 'Lihat dan batalkan transaksi shift ini', onSelect: () => router.push({ name: 'cashier-history' }) },
  { label: 'Kas Masuk / Keluar', description: 'Catat uang yang masuk atau keluar dari laci', onSelect: () => (modals.value.cash = true) },
  { label: 'Tutup Shift', description: 'Hitung kas dan akhiri shift', onSelect: () => router.push({ name: 'shift-close' }) },
  {
    label: 'Keluar',
    description: 'Shift tetap terbuka, bisa dilanjutkan nanti',
    onSelect: async () => {
      await auth.logout()
      router.replace({ name: 'cashier-login' })
    },
  },
]

useShortcuts({ F9: openPay, F4: openHold }, () => !anyModalOpen.value)

onMounted(() => {
  catalog.load()
  held.refresh()
  refreshTables()
  settingsStore.ensureLoaded().catch(() => {})
  outletStore.ensureLoaded().catch(() => {})
})
</script>

<template>
  <CashierLayout :outlet-code="outletCode" :cashier-name="auth.user?.name ?? ''" :shift-opened-at="shiftStore.shift?.opened_at">
    <template #header-actions>
      <Button v-if="hasTables" size="lg" @click="modals.tables = true">Meja</Button>
      <Button size="lg" @click="held.refresh(); modals.held = true">
        Tersimpan<span v-if="held.sales.value.length" class="ml-1 tabular-nums">({{ held.sales.value.length }})</span>
      </Button>
      <CashierMenu :items="menuItems" />
    </template>

    <ProductPanel ref="productPanel" :catalog="catalog" :quantities="quantities" @add="addProduct" />
    <CartPanel :fnb="hasTables" :holding="held.saving.value" @pay="openPay" @hold="openHold" @discard-held="modals.discard = true" @pick-customer="modals.customer = true" @discount="modals.discount = true" @tables="modals.tables = true" />

    <PaymentModal v-if="outletId" v-model:open="modals.pay" :outlet-id="outletId" @paid="onPaid" @stock-error="onStockError" />
    <ProductOptionsModal
      v-model:open="modals.options"
      :product="optionsProduct"
      :initial-variant-id="optionsProduct?.matched_variant_id ?? null"
      @add="(opts) => cart.add(optionsProduct, 1, opts)"
    />
    <SaleSuccessModal v-model:open="modals.success" :sale="lastSale" @new="newTransaction">
      <template #actions="{ sale }">
        <template v-if="!sale.offline">
          <WhatsAppShareButton :sale-id="sale.id" />
          <Button size="lg" @click="printSale(sale.id)">Cetak Struk</Button>
        </template>
        <span v-else class="col-span-2 text-sm text-muted">Struk bisa dicetak dari Riwayat setelah tersinkron.</span>
      </template>
    </SaleSuccessModal>
    <HoldModal v-model:open="modals.hold" :initial-label="cart.holdLabel" :saving="held.saving.value" @save="saveHold" />
    <HeldListModal
      v-model:open="modals.held"
      :sales="held.sales.value"
      :cart-has-items="!cart.isEmpty"
      @resume="(sale) => { held.resume(sale); modals.held = false }"
    />
    <CashMovementModal v-model:open="modals.cash" />
    <CustomerPickerModal v-model:open="modals.customer" @select="cart.setCustomer" />
    <AttendanceModal v-model:open="modals.attendance" :outlet-id="outletId" />
    <TablesModal v-if="outletId" v-model:open="modals.tables" :outlet-id="outletId" @changed="held.refresh()" />
    <DiscountModal v-if="outletId" v-model:open="modals.discount" :outlet-id="outletId" />
    <ConfirmModal
      v-model:open="modals.discard"
      title="Buang transaksi tersimpan ini?"
      confirm-label="Buang Transaksi"
      busy-label="Membuang..."
      @confirm="discardHeld"
    >
      <p>Isi keranjang dan transaksi tersimpan "{{ cart.holdLabel || 'tanpa label' }}" dihapus. Tidak ada stok atau uang yang berubah.</p>
    </ConfirmModal>
  </CashierLayout>
</template>
