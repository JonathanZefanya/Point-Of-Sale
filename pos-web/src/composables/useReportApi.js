import { downloadFile } from '@/lib/download'
import http from '@/lib/http'

/** Pemanggilan API laporan (CLAUDE.md §5: Laporan). */
export function useReportApi() {
  return {
    dashboard: (params) => http.get('/reports/dashboard', { params }).then((r) => r.data.data),
    sales: (params) => http.get('/reports/sales', { params }).then((r) => r.data),
    payments: (params) => http.get('/reports/payments', { params }).then((r) => r.data),
    /** Laporan lanjutan (2.11): products, categories, cashiers, profit, stock, voids → { data, summary } */
    detail: (type, params) => http.get(`/reports/${type}`, { params }).then((r) => r.data),

    /** Unduh Excel lewat http (agar token terkirim), lalu simpan sebagai file. */
    download: (report, params) => downloadFile(`/reports/${report}`, { ...params, export: 'xlsx' }, `laporan-${report}.xlsx`),
  }
}
