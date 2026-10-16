# POS — Point of Sale

Aplikasi kasir untuk toko retail dan F&B: layar kasir yang bisa dipakai tanpa pelatihan, back office untuk produk, stok, laporan, dan multi-outlet.

| Bagian | Teknologi | Folder |
|---|---|---|
| Backend (REST API) | Laravel 12, PHP 8.3, MySQL 8, Sanctum | [`pos-restapi/`](pos-restapi) |
| Frontend (back office + kasir) | Vue 3, Vite, Pinia, Tailwind CSS | [`pos-web/`](pos-web) |
| Infrastruktur | Docker Compose, 3 container | [`docker/`](docker), [`docker-compose.yml`](docker-compose.yml) |


---

## Fitur

**Kasir**
- Cari / scan barcode, varian & modifier, catatan per item, hapus dengan *Batalkan*
- Bayar tunai (nominal cepat & kembalian), QRIS, debit, kredit, e-wallet, transfer, split payment, poin pelanggan
- QRIS dinamis via Midtrans (status lunas dicek otomatis)
- Simpan & lanjutkan transaksi, bill per meja, pindah & gabung meja, tipe pesanan (F&B)
- Voucher, promo otomatis, diskon manual dengan PIN supervisor
- Void & refund dengan PIN supervisor, cetak struk 58/80 mm, kirim struk via WhatsApp
- Mode offline: transaksi disimpan di perangkat dan disinkronkan otomatis saat online
- Buka/tutup shift, kas masuk/keluar, absensi
- Layar dapur (Kitchen Display) dengan cetak tiket

**Back office**
- Produk, kategori, varian, modifier, harga & ketersediaan per outlet, import/export Excel, cetak label barcode
- Stok: kartu stok, penyesuaian, stock opname (draf), transfer antar outlet, pembelian (PO) & supplier dengan harga modal rata-rata
- Pelanggan & poin loyalty, promo & voucher, meja
- Dashboard & laporan: penjualan, metode bayar, per produk/kategori/kasir, laba kotor, stok, void/refund, komisi — semua bisa export Excel
- Karyawan & hak akses (Pemilik, Manajer, Kasir, Gudang), absensi, audit log
- Pengaturan: bisnis, pajak, pembulatan, struk, integrasi (Midtrans, SMTP), email laporan terjadwal

---

## Arsitektur Docker

```
 Browser ──► pos-web      :5173  (172.20.0.10)  ─┐  network pos_app
         └─► pos-restapi  :8000  (172.20.0.20)  ─┘
                   │ 172.21.0.20
                   ▼                                network pos_db (internal)
             pos-mysql           (172.21.0.30)   ← tanpa port ke host
```

- Browser memanggil API langsung di `localhost:8000`, jadi port API wajib di-publish.
- MySQL hanya bisa diakses dari `pos-restapi`: dua lapis pengaman — network `pos_db` bersifat internal **dan** user MySQL `pos` dikunci ke IP `172.21.0.20` ([`docker/mysql/`](docker/mysql)).
- Data MySQL disimpan di volume `pos_mysql_data`.

---

## Menjalankan (development)

Prasyarat: Docker Desktop.

```bash
# 1. Salin file environment
cp .env.example .env                              # DB_PASSWORD & MYSQL_ROOT_PASSWORD
cp pos-restapi/.env.example pos-restapi/.env      # DB_PASSWORD harus sama dengan .env root
cp pos-web/.env.example pos-web/.env

# 2. Jalankan semua container
docker compose up -d --build

# 3. Sekali saja: kunci aplikasi + tabel + data awal
docker compose exec pos-restapi php artisan key:generate
docker compose exec pos-restapi php artisan migrate --seed
```

| Alamat | Untuk |
|---|---|
| http://localhost:5173 | Back office (login email) |
| http://localhost:5173/kasir/login | Layar kasir (pilih nama + PIN) |
| http://localhost:8000/api/v1 | REST API |

**Akun demo** (dari seeder):

| Peran | Login |
|---|---|
| Pemilik | `owner@pos.test` / `password` (PIN supervisor `654321`) |
| Kasir | pilih **Rina**, PIN `123456` |

> Port 3306 di host **bukan** MySQL container ini (sengaja tidak di-publish). Bila ada MySQL lain di host, itu milik layanan Windows/instalasi lain.

---

## Perintah sehari-hari

```bash
# Backend
docker compose exec pos-restapi php artisan test                     # tes (SQLite in-memory)
docker compose exec -e DB_CONNECTION=mysql -e DB_DATABASE=pos_testing \
  pos-restapi php artisan test                                       # tes di MySQL (wajib untuk query agregat/lock)
docker compose exec pos-restapi ./vendor/bin/pint                    # format kode PHP
docker compose exec pos-restapi php artisan migrate                  # migration baru

# Frontend
docker compose exec pos-web npm run lint
docker compose exec pos-web npm run build

# Log & berhenti
docker compose logs -f pos-restapi
docker compose down            # tambahkan -v untuk menghapus data database
```

---

## Konfigurasi integrasi

Semua diatur pemilik di **Pengaturan → Integrasi / Notifikasi** di aplikasi. Kunci rahasia disimpan terenkripsi dan tidak pernah dikirim ke browser. Nilai di `pos-restapi/.env` dipakai sebagai cadangan bila pengaturan di aplikasi kosong.

**Midtrans (QRIS dinamis)**
1. Ambil **Server Key** di dashboard Midtrans → *Settings → Access Keys* (sandbox diawali `SB-Mid-server-`).
2. Tempel di Pengaturan → Integrasi, pilih mode Sandbox/Produksi, simpan.
3. Isi *Notification URL* di dashboard Midtrans dengan `{APP_URL}/api/v1/payments/midtrans/notification`. Tanpa webhook (mis. di localhost), status tetap dicek otomatis dari layar kasir.

**Email (laporan harian & stok menipis)**
1. Isi SMTP di Pengaturan → Integrasi (Gmail: `smtp.gmail.com`, TLS 587, *App Password*).
2. Isi penerima & aktifkan laporan di Pengaturan → Notifikasi, lalu uji dengan **Kirim Contoh Sekarang**.
3. Jalankan scheduler agar email terkirim otomatis (07:00 laporan harian, 08:00 stok menipis):
   ```bash
   docker compose exec pos-restapi php artisan schedule:work
   ```

**Link struk WhatsApp** memakai `FRONTEND_URL` di `pos-restapi/.env` — isi dengan alamat web yang bisa dibuka pelanggan.

---

## Produksi

Image produksi: API di nginx + php-fpm (port 8000), web berupa file statis di nginx (port 8080).

```bash
# 1. Siapkan environment produksi API (APP_ENV=production, APP_DEBUG=false, APP_KEY, APP_URL, FRONTEND_URL, DB_*)
cp pos-restapi/.env.example pos-restapi/.env.production

# 2. Sesuaikan VITE_API_URL di docker-compose.prod.yml dengan alamat API yang diakses browser

# 3. Build & jalankan (migration dijalankan otomatis oleh entrypoint)
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# 4. Sekali saja: data awal
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec pos-restapi php artisan db:seed --force
```

- Gambar produk & log disimpan di volume `pos_storage`.
- `VITE_API_URL` ditanam saat build — ganti alamat API berarti build ulang `pos-web`.
- Ganti password akun demo setelah login pertama.

---

## Struktur repo

```
studi-case/
├── GUIDE-TEST.md           panduan demo deployment
├── docker-compose.yml      development (3 container, IP statis)
├── docker-compose.prod.yml override produksi
├── docker/                 Dockerfile, nginx, entrypoint, init MySQL
├── pos-restapi/            Laravel: app/{Http,Services,Models,Enums}, tests/Feature
└── pos-web/                Vue: src/{views,components,stores,composables,lib}
```
