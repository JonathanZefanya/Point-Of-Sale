import { nextTick, ref } from 'vue'
import http from '@/lib/http'

// State bersama: satu area cetak global (ReceiptPrintArea di App.vue) untuk seluruh aplikasi
const receipt = ref(null)
const reprint = ref(false)

// Kosongkan setelah dicetak agar tidak ikut tercetak bersama laporan lain (mis. laporan shift)
if (typeof window !== 'undefined') {
  window.addEventListener('afterprint', () => {
    receipt.value = null
    document.getElementById('receipt-page-size')?.remove() // kembali ke ukuran kertas default
  })
}

/** Atur ukuran kertas thermal untuk window.print(). */
function setPaperWidth(mm) {
  let style = document.getElementById('receipt-page-size')
  if (!style) {
    style = document.createElement('style')
    style.id = 'receipt-page-size'
    document.head.appendChild(style)
  }
  style.textContent = `@media print { @page { size: ${mm}mm auto; margin: 0; } .receipt-print { width: ${mm}mm; } }`
}

/**
 * Cetak struk via window.print() + CSS print (FE-1.11).
 * Data struk diambil dari GET /sales/{id}/receipt — sudah final dari server.
 */
export function usePrint() {
  async function printSale(saleId, { isReprint = false } = {}) {
    const { data } = await http.get(`/sales/${saleId}/receipt`)
    receipt.value = data.data
    reprint.value = isReprint
    setPaperWidth(data.data.paper_width)
    await nextTick()
    window.print()
    return data.data
  }

  /** Ambil data struk tanpa mencetak (mis. untuk cek pengaturan auto print). */
  async function fetchReceipt(saleId) {
    const { data } = await http.get(`/sales/${saleId}/receipt`, { silent: true })
    return data.data
  }

  return { receipt, reprint, printSale, fetchReceipt }
}
