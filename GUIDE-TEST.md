# Panduan Demo: Deployment POS Tiga Server

Panduan langkah demi langkah untuk lima demo di slide terakhir, lengkap dengan perintah, hasil yang diharapkan, kalimat penjelasan, dan rencana cadangan.

Semua perintah dijalankan di PowerShell, dari folder `studi-case`.

---

## 0. Persiapan

### Sehari sebelumnya (sekali saja)

**Pastikan image development dan produksi sudah ter-build**, supaya perpindahan mode saat demo 3 tidak perlu build ulang:

```powershell
docker compose build
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
```

Image FE produksi sengaja tidak berisi netcat, jadi uji `nc` dijalankan dari image FE development. Coba sekali alur demo 3 untuk tahu berapa lama perpindahan modenya.

### 30 menit sebelum presentasi

1. **Pastikan Docker Desktop menyala.**
2. **Matikan MySQL Windows** supaya port 3306 di laptop bersih. Buka PowerShell sebagai Administrator:

   ```powershell
   Stop-Service MySQL80
   ```

3. **Buat perintah singkat** di terminal yang akan dipakai demo, supaya tidak mengetik perintah compose yang panjang:

   ```powershell
   function dc { docker compose -f docker-compose.yml -f docker-compose.prod.yml @args }
   ```

   Setelah ini, `dc ps` sama dengan perintah compose lengkap. Fungsi ini hilang kalau terminal ditutup, jadi jalankan ulang di terminal baru.

4. **Jalankan mode produksi:**

   ```powershell
   dc up -d
   ```

5. **Cek cepat:**
   - `dc ps`: ketiga container berstatus `Up`, `pos-mysql` berstatus `healthy`.
   - Buka `http://localhost:8080`, login, dan pastikan dashboard tampil. Lalu **logout** supaya demo 2 dimulai dari halaman login.
   - `Test-NetConnection localhost -Port 3306` bernilai `False`.

6. **Siapkan layar:**
   - Satu jendela browser khusus demo, hanya berisi tab `localhost:8080`.
   - Satu jendela terminal, ukuran huruf diperbesar (Ctrl + scroll) supaya terbaca saat share screen.
   - Matikan notifikasi Windows dan WhatsApp Web.
   - Saat share screen, pilih **jendela tertentu**, bukan seluruh layar.

---

## Demo 1: Tiga container, dua jaringan

**Perintah**

```powershell
dc ps
```

```powershell
docker network inspect pos_app --format '{{range .Containers}}{{.Name}} {{.IPv4Address}}{{println}}{{end}}'
docker network inspect pos_db --format '{{range .Containers}}{{.Name}} {{.IPv4Address}}{{println}}{{end}}'
```

**Hasil yang diharapkan**

- `dc ps`: tiga container `Up`. Kolom PORTS: `pos-web` punya `8080->80`, `pos-restapi` punya `8000->80`, `pos-mysql` **kosong**.
- `pos_app`: `pos-web 172.20.0.10/24` dan `pos-restapi 172.20.0.20/24`.
- `pos_db`: `pos-mysql 172.21.0.30/24` dan `pos-restapi 172.21.0.20/24`.

**Penjelasan**

> “Setiap container saya perlakukan sebagai satu server dengan IP privat sendiri. Ada dua jaringan: pos_app untuk FE dan BE, pos_db untuk BE dan DB. BE satu-satunya yang ada di dua jaringan. Perhatikan juga, MySQL tidak membuka port sama sekali.”

**Kalau gagal**

- Container tidak `Up`: `docker logs pos-restapi --tail 30` untuk melihat penyebabnya.
- Perintah `--format` error: jalankan `docker network inspect pos_db` tanpa format, lalu tunjukkan bagian `"Containers"`.

---

## Demo 2: Login dan dashboard

**Langkah**

1. Buka `http://localhost:8080`.
2. Login dengan akun owner.
3. Tunjukkan dashboard, lalu buka satu menu lain, misalnya **Produk**.
4. Tekan **refresh** di halaman Produk.
5. Opsional: buka DevTools (F12), tab **Network**, lalu tunjukkan request ke `localhost:8000/api/v1/...`.

**Hasil yang diharapkan**

Dashboard tampil dengan data. Halaman Produk tetap tampil setelah di-refresh.

**Penjelasan**

> “FE ini file statis yang disajikan Nginx di port 8080. Saat saya login, browser memanggil API langsung ke port 8000, terlihat di tab Network. Makanya BE mengatur CORS untuk origin localhost:8080. Halaman tetap tampil saat di-refresh karena Nginx mengarahkan semua route ke index.html.”

**Kalau gagal**

- “Tidak dapat terhubung ke server”: cek `CORS_ALLOWED_ORIGINS` di `pos-restapi/.env.production` harus `http://localhost:8080`, lalu `dc up -d --force-recreate pos-restapi`.
- Halaman putih atau error: `docker logs pos-restapi --tail 30`.

---

## Demo 3: FE tidak bisa menjangkau DB (mode development)

Image FE produksi hanya berisi Nginx dan file statis, tanpa netcat. Untuk demo ini, **hanya container FE** yang ditukar sementara ke image development. Nama container, jaringan, dan IP-nya tetap sama (`pos-web`, `pos_app`, `172.20.0.10`), jadi hasilnya sama dengan FE produksi. BE dan DB tidak disentuh.

**Pindah FE ke mode development**

```powershell
docker compose up -d --no-deps pos-web
```

`--no-deps` memastikan hanya `pos-web` yang dibuat ulang, BE dan DB tetap berjalan.

**Perintah**

```powershell
docker exec pos-web nc -zv -w 3 172.21.0.30 3306
docker exec pos-web nc -zv -w 3 pos-mysql 3306
```

**Hasil yang diharapkan**

- Perintah pertama: `timed out` setelah sekitar 3 detik.
- Perintah kedua: `bad address 'pos-mysql'`.

**Pembanding: BE bisa**

```powershell
docker exec pos-restapi php artisan db:show
```

Hasilnya menampilkan host `172.21.0.30`, database `pos`, dan daftar tabel. Tekan `Ctrl + C` kalau daftarnya terlalu panjang.

**Kembalikan FE ke mode produksi**

```powershell
dc up -d --no-deps pos-web
```

Cek `dc ps`: `pos-web` kembali memakai port `8080`.

**Penjelasan**

> “Image FE produksi hanya berisi Nginx, jadi untuk uji ini FE saya tukar sementara ke image development. IP dan jaringannya tetap sama. Dari container FE, saya coba menjangkau MySQL lewat IP-nya: timeout, karena FE tidak terhubung ke jaringan pos_db. Lewat nama container pun tidak dikenali, karena DNS Docker hanya berlaku di jaringan yang sama. Sebaliknya dari BE, koneksinya berhasil.”

**Kalau gagal**

- `pos-web` lama menyala di mode development (`npm ci` berjalan): tunggu sampai `docker logs pos-web` tidak bergerak. Perintah `nc` sebenarnya sudah bisa dijalankan selama container berstatus `Up`.
- Waktu terbatas: lewati demo ini dan tunjukkan slide pengujian nomor 5 dan 6.

---

## Demo 4: Login MySQL dari host lain ditolak

**Perintah**

Tunjukkan aturan user-nya dulu, masuk sebagai root:

```powershell
docker exec -it pos-mysql mysql -uroot -p -e "SELECT user, host FROM mysql.user;"
```

Lalu coba login sebagai user aplikasi dari dalam container DB:

```powershell
docker exec -it pos-mysql mysql -upos -p
```

Masukkan password user `pos` yang **benar**.

**Hasil yang diharapkan**

- Tabel user: `pos` dengan host `172.21.0.20`, `root` dengan host `localhost`.
- Login: `ERROR 1045 (28000): Access denied for user 'pos'@'localhost'`.

**Penjelasan**

> “User aplikasi hanya boleh login dari 172.21.0.20, yaitu IP BE. Saya coba login dari dalam container DB dengan password yang benar, dan tetap ditolak, karena koneksinya datang dari localhost. Jadi password yang bocor pun tidak cukup, koneksinya harus datang dari server BE.”

**Kalau gagal**

- Perintah dengan `-e "..."` bermasalah di PowerShell: masuk interaktif dengan `docker exec -it pos-mysql mysql -uroot -p`, lalu ketik `SELECT user, host FROM mysql.user;` dan `exit`.

---

## Demo 5: DB mati, lalu pulih

**Perintah**

```powershell
docker stop pos-mysql
```

Refresh dashboard di browser.

```powershell
docker start pos-mysql
docker inspect --format '{{.State.Health.Status}}' pos-mysql
```

Ulangi perintah `docker inspect` sampai hasilnya `healthy` (biasanya kurang dari 30 detik), lalu refresh dashboard lagi.

**Hasil yang diharapkan**

- Saat DB mati: “Dashboard gagal dimuat. Terjadi kesalahan di server.”
- Setelah `healthy`: dashboard kembali normal, data tetap utuh, tanpa me-restart BE.

**Penjelasan**

> “Saat DB mati, aplikasi tidak crash. BE masih hidup dan membalas error 500 yang rapi. Ini berbeda dengan error CORS, yang sama sekali tidak memberi respons. Jadi dari pesannya saja kita bisa menebak lapisan mana yang bermasalah. Setelah DB menyala, BE langsung pulih karena Laravel membuka koneksi baru di setiap request.”

**Kalau gagal**

- Dashboard belum pulih setelah `healthy`: tunggu 10 detik lalu refresh lagi. Kalau masih gagal, `dc restart pos-restapi`.

---

## Pertanyaan umum

| Pertanyaan | Inti jawaban |
|---|---|
| Kenapa FE dan BE beda port di localhost, bukan beda IP? | Browser berjalan di laptop, jadi container dijangkau lewat port yang dibuka ke laptop. Antar container tetap memakai IP privat. Di produksi, port ini diganti domain. |
| Kenapa tidak satu container saja? | IP dimiliki container. Satu container berarti semua saling memanggil lewat localhost, jadi tidak ada pemisahan dan pembatasan akses per server. |
| Bedanya dengan server sungguhan? | Container berbagi satu mesin. Di produksi tiap lapis di VM atau VPS sendiri, dengan firewall, DNS, dan HTTPS. Konfigurasi aplikasinya tetap sama, hanya nilainya berganti. |
| Kalau alamat BE berubah? | Ubah `VITE_API_URL` lalu build ulang FE, dan ubah CORS di BE kalau alamat FE ikut berubah. |
| Password database disimpan di mana? | Di `.env.production` yang tidak masuk image karena diabaikan `.dockerignore`, dan dikirim lewat `env_file` saat container berjalan. |
| Apa yang perlu diperbaiki berikutnya? | Batasi user `pos` hanya ke database `pos` (tanpa `pos_testing`), tambah CI/CD, dan backup terjadwal. |

---

## Setelah presentasi

Kalau butuh MySQL Windows lagi untuk proyek lain, jalankan dari PowerShell Administrator:

```powershell
Start-Service MySQL80
```

Untuk mematikan aplikasi POS tanpa menghapus data:

```powershell
dc down
```

Jangan gunakan `dc down -v`, karena `-v` menghapus volume dan seluruh data MySQL.