# SIRKEL

**SIRKEL — Sistem Sirkular Elektronik Kota** adalah platform web untuk membantu warga mengelola elektronik yang tidak lagi digunakan melalui jalur **perbaikan, pemulihan material, penggunaan kembali, dan donasi** dengan proses yang dapat ditelusuri dari pengajuan sampai hasil akhir.

Platform ini dikembangkan dengan fokus implementasi di **Surabaya**, dengan studi dan demo yang banyak menggunakan area **Gunung Anyar**. SIRKEL tidak memposisikan diri sebagai marketplace servis biasa; sistem berfungsi sebagai orkestrator alur circular e-waste yang menghubungkan warga, mitra penanganan, dan admin dalam satu chain-of-custody yang terdokumentasi.

## Ringkasan

SIRKEL mengelola alur utama:

```text
Identifikasi Barang
        ↓
Cek Kondisi
        ↓
Rekomendasi Jalur Sirkular
        ↓
Matching Mitra
        ↓
Penawaran & Penyerahan
        ↓
Penanganan / Transfer Antar-Mitra
        ↓
Outcome Terverifikasi
        ↓
Riwayat & Digital Passport
```

Outcome utama yang didukung meliputi:

- **Repair / Refurbish** — barang dipertahankan sebagai perangkat utuh jika masih layak diperbaiki.
- **Recovery** — material atau komponen dipulihkan ketika penggunaan ulang perangkat tidak lagi layak.
- **Reuse / Donation** — perangkat yang masih layak disalurkan untuk digunakan kembali.
- **Special Handling** — penanganan khusus untuk kondisi berisiko seperti baterai bermasalah atau perangkat pendingin tertentu.

---

## Fitur Utama

### 1. Warga

Warga dapat:

- registrasi akun dan verifikasi email dengan OTP;
- login menggunakan email/password atau Google OAuth;
- melengkapi profil dan data wilayah;
- memilih tampilan Light / Dark / System;
- mendaftarkan elektronik secara manual;
- menggunakan kamera atau galeri untuk foto barang;
- memakai **Pengenalan Barang berbasis AI** secara opsional;
- menyimpan barang ke **Keranjang Elektronik** tanpa batas jumlah draft;
- memproses maksimal **3 kelompok barang** dalam satu sesi Standard;
- menjalankan questionnaire kondisi secara bertahap dengan autosave;
- melanjutkan sesi pemeriksaan yang belum selesai;
- memakai **Bulk AI Pro** untuk beberapa barang sekaligus;
- menentukan cara penyerahan satu kali untuk beberapa barang;
- memilih mitra berdasarkan capability, kategori, radius, dan jarak;
- menghubungi mitra melalui WhatsApp tanpa melewati status resmi sistem;
- menerima / menolak penawaran;
- meminta penawaran ulang dari mitra yang sama;
- mengganti mitra tanpa kehilangan data penyerahan yang telah diisi;
- memantau progres barang;
- melihat bukti donasi setelah barang benar-benar disalurkan;
- melihat dampak dan riwayat circular barang;
- mengelola kuota AI dan membuat permintaan top up manual.

### 2. Keranjang & Pemeriksaan Multi-Barang

Keranjang Elektronik berfungsi sebagai penyimpanan draft sebelum pemeriksaan.

- Jumlah draft di keranjang **tidak dibatasi**.
- Satu proses Standard dapat memilih maksimal **3 kelompok barang**.
- Setiap kelompok tetap menjadi asset logis terpisah agar Rule Engine dan matching tetap aman.
- Jawaban questionnaire disimpan ke database sehingga progres tidak hilang ketika browser ditutup.
- Setelah semua barang selesai diperiksa, warga dapat mengatur logistik penyerahan **sekali** untuk seluruh sesi.

Jika satu mitra mampu menangani semua barang, sistem dapat menawarkan:

> **Pilih Mitra Ini untuk Semua**

Jika tidak ada satu mitra yang cocok untuk seluruh barang, sistem menampilkan pilihan mitra per asset tanpa meminta warga mengisi ulang lokasi, metode, dan jadwal penyerahan.

### 3. Bulk AI Pro

Bulk AI dirancang untuk mempercepat intake ketika warga memiliki banyak elektronik.

Flow utama:

```text
Upload 1–3 Foto
      ↓
AI Mengenali Banyak Barang
      ↓
Grouping & Review
      ↓
Edit / Hapus / Tambah Manual
      ↓
Adaptive Bulk Questionnaire
      ↓
Rule Engine
      ↓
Review & Penyerahan
```

Aturan penting:

- maksimal **5 kelompok barang** per sesi Bulk AI;
- barang sejenis dapat digabung menjadi satu kelompok bila penanganannya masih kompatibel;
- jumlah unit fisik tidak sama dengan jumlah kelompok;
- hasil AI selalu dapat dikoreksi warga;
- satu sesi Bulk AI hanya mengurangi **1 kuota Bulk AI**;
- resume sesi yang sama tidak memotong kuota kembali;
- Adaptive Questionnaire menggunakan **sesedikit mungkin pertanyaan yang diperlukan**;
- **15 pertanyaan adalah batas maksimum absolut**, bukan target;
- pertanyaan dapat berlaku ke beberapa barang sekaligus;
- safety signal penting tetap dijaga server-side;
- AI tidak menjadi authority untuk keputusan circular akhir.

### 4. AI Assistance

AI dipakai sebagai **asisten opsional** untuk mempercepat pengenalan barang dan penyusunan informasi. Keputusan penanganan akhir tetap berasal dari data kondisi dan pemeriksaan mitra.

Fitur AI warga:

| Fitur | Fungsi |
| --- | --- |
| Pengenalan Barang | Membantu mengenali kategori dan observasi awal dari foto |
| Penyusunan Catatan Kondisi | Membantu merangkum jawaban questionnaire Standard |
| Bulk AI | Multi-object intake + adaptive questionnaire |

SIRKEL memiliki:

- ledger penggunaan AI;
- batas budget bulanan;
- timeout dan retry guard;
- execution-window protection untuk shared hosting;
- cache hasil AI pada flow yang mendukung;
- alur manual tetap tersedia jika bantuan AI tidak digunakan atau sedang tidak tersedia;
- kuota per akun;
- top up manual melalui WhatsApp dan approval Admin.

Default demo quota:

```text
Pengenalan Barang            5 penggunaan
Penyusunan Catatan Kondisi  20 penggunaan
Bulk AI                      3 sesi
```

Nilai default tersebut dapat diubah melalui halaman pengaturan Admin.

### 5. Matching Mitra

Matching bersifat deterministik dan mempertimbangkan:

- status verifikasi mitra;
- status operasional mitra;
- availability menerima request;
- kategori elektronik;
- capability mitra;
- jenis outcome yang dibutuhkan;
- radius pickup;
- jarak antara warga dan mitra;
- kebutuhan special handling.

Sistem membedakan **Direkomendasikan** dan **Opsi lainnya**, tetapi hanya menampilkan kandidat yang benar-benar eligible.

### 6. Penawaran & Negosiasi Ulang

Mitra harus menerima request terlebih dahulu sebelum dapat memberikan penawaran.

Jika warga menolak penawaran, warga dapat memilih:

1. **Minta Penawaran Baru** dari mitra yang sama;
2. **Ganti Mitra** tanpa mengisi ulang data penyerahan;
3. **Batalkan Penyerahan**.

Penawaran baru disimpan sebagai versi baru sehingga histori negosiasi tidak ditimpa.

### 7. Mitra

Mitra memiliki dashboard operasional untuk:

- melihat Permintaan Masuk;
- menerima atau menolak request;
- mengatur availability;
- memberikan penawaran;
- mengusulkan jadwal;
- mengonfirmasi penerimaan fisik barang;
- melihat Barang Ditangani;
- melakukan assessment teknis;
- menentukan hasil circular;
- mengalihkan barang ke mitra lain;
- menerima / menolak transfer;
- memecah batch jika diperlukan;
- mengunggah Bukti Donasi;
- melaporkan masalah.

Satu akun mitra mewakili satu lokasi operasional dengan radius pickup.

### 8. Lifecycle Donasi & Bukti Penyaluran

Untuk outcome Donasi, barang **tidak dianggap selesai hanya karena sudah diterima mitra**.

Flow donasi:

```text
Warga Menyerahkan Barang
        ↓
Mitra Menerima
        ↓
Pemeriksaan / Persiapan
        ↓
Menunggu Penyaluran
        ↓
Mitra Menyalurkan Barang
        ↓
Upload Bukti Donasi
        ↓
Donasi Selesai
```

Bukti Donasi mencatat:

- foto penyaluran;
- waktu penyaluran;
- lokasi perangkat saat bukti dicatat;
- accuracy geolocation bila tersedia;
- jenis penerima;
- nama / identitas penerima yang relevan;
- catatan penyaluran.

Untuk penerima individu, data sensitif tidak diekspos pada passport publik. Koordinat presisi juga tidak ditampilkan ke publik.

### 9. Transfer Antar-Mitra

Barang dapat dipindahkan antar-mitra ketika satu lokasi tidak dapat menyelesaikan seluruh proses circular.

Contoh:

```text
Warga → Mitra Repair → Mitra Donation → Penerima Akhir
```

atau:

```text
Warga → Mitra Repair → Mitra Recovery
```

Sistem menjaga:

- chain-of-custody;
- histori pemegang barang;
- pending transfer;
- proteksi transfer loop langsung;
- validasi capability target;
- outcome awal warga agar tidak ditutup oleh jalur yang salah.

### 10. Admin

Admin dapat:

- melihat dashboard platform;
- memverifikasi calon mitra;
- mengaktifkan / menonaktifkan mitra;
- meninjau KTP dari private storage;
- mengelola capability mitra;
- mengelola kategori dan kelompok perangkat;
- mengelola questionnaire warga dan mitra;
- mengelola Circular Rules;
- melihat laporan masalah;
- melihat audit log;
- memonitor AI usage dan budget;
- mengatur kuota serta harga AI;
- memproses permintaan top up;
- mengatur nomor WhatsApp Admin;
- menyinkronkan data wilayah Surabaya melalui BinderByte.

---

## Digital Material Passport

Setiap asset memiliki histori lifecycle yang dapat digunakan sebagai Digital Material Passport.

Passport publik hanya menampilkan informasi yang aman untuk dipublikasikan, seperti:

- jenis perangkat;
- perjalanan circular;
- event penanganan;
- hasil akhir;
- bukti outcome dalam bentuk metadata aman.

Data pribadi warga, nomor telepon, alamat detail, koordinat presisi, KTP, dan identitas penerima individu tidak dimasukkan ke passport publik.

---

## Lokasi & Peta

SIRKEL menggunakan:

- **Leaflet**;
- **OpenStreetMap**;
- Browser Geolocation API;
- Haversine distance;
- input titik lewat peta;
- import link Google Maps;
- link koordinat canonical ke Google Maps.

Master wilayah Surabaya tersedia di database agar dropdown kecamatan/kelurahan tetap dapat digunakan walaupun API pihak ketiga tidak tersedia.

---

## Stack Teknologi

### Backend

- PHP `^8.3`
- Laravel `^13`
- MySQL / MariaDB
- Laravel Socialite
- Endroid QR Code

### Frontend

- Blade
- Tailwind CSS
- Vite
- JavaScript
- Axios
- Leaflet + OpenStreetMap

### Integrasi Opsional

- OpenAI-compatible Responses API
- Google OAuth
- BinderByte Region API
- SMTP email
- Browser Geolocation
- WhatsApp deep-link

---

## Struktur Project

```text
app/
├── Enums/                 Enum domain
├── Http/Controllers/      Controller aplikasi
├── Http/Middleware/       Role, profile, verification guards
├── Models/                Model Eloquent
├── Notifications/         Email / in-app notification
├── Services/              AI, matching, notification, circular logic
└── Support/               Helper domain

bootstrap/                 Bootstrap Laravel
config/                    Konfigurasi aplikasi
database/
├── factories/
├── migrations/
└── seeders/
public/                    Front controller dan public assets
resources/
├── css/
├── js/
└── views/
routes/                    Web routes
storage/                   Runtime/private storage
tests/Feature/             Regression & feature tests
```

---

# Instalasi Lokal

## Requirement

Pastikan tersedia:

- PHP 8.3 atau lebih baru;
- Composer 2;
- MySQL / MariaDB;
- Node.js + npm;
- ekstensi PHP umum Laravel seperti OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON, Fileinfo, dan cURL.

## 1. Clone Repository

```bash
git clone https://github.com/AzizWira/sirkel.git
cd sirkel
```

## 2. Install Dependency PHP

```bash
composer install
```

## 3. Install Dependency Frontend

```bash
npm install
```

## 4. Buat Environment

Linux/macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generate key:

```bash
php artisan key:generate
```

## 5. Konfigurasi Database

Contoh:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sirkel
DB_USERNAME=root
DB_PASSWORD=
```

## 6. Migration dan Seeder

Untuk database development/demo baru:

```bash
php artisan migrate:fresh --seed
```

Untuk database existing:

```bash
php artisan migrate
```

> `migrate:fresh --seed` menghapus seluruh isi database. Jangan gunakan pada production kecuali memang ingin reset total.

## 7. Build Frontend

```bash
npm run build
```

Untuk development dengan Vite:

```bash
npm run dev
```

## 8. Storage Link

```bash
php artisan storage:link
```

## 9. Bersihkan Cache

```bash
php artisan optimize:clear
```

## 10. Jalankan Development Server

```bash
php artisan serve
```

Default:

```text
http://127.0.0.1:8000
```

---

# Environment Variables

Gunakan `.env.example` sebagai referensi. Jangan commit `.env` production ke Git.

## Application

```env
APP_NAME=SIRKEL
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
```

Pada production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.com
```

## Session / Queue / Cache

```env
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Jika queue worker belum digunakan pada environment development, konfigurasi dapat disesuaikan. Untuk production direkomendasikan memakai queue worker agar email/notifikasi yang dapat diantrikan tidak menahan response HTTP.

## Email

Contoh SMTP:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

## Google OAuth

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
```

Pastikan callback yang sama didaftarkan pada Google Cloud Console.

## AI Provider

```env
OPENAI_API_KEY=
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_MODEL_DEFAULT=
OPENAI_MODEL_ESCALATION=
OPENAI_MODEL_COMPLEX=
OPENAI_MONTHLY_BUDGET_USD=20
OPENAI_ESCALATION_CONFIDENCE=0.65
OPENAI_IMAGE_DETAIL=low
OPENAI_CONNECT_TIMEOUT=20
OPENAI_REQUEST_TIMEOUT=60
OPENAI_EXECUTION_TIMEOUT=150
OPENAI_EXECUTION_FALLBACK_BUFFER=8
OPENAI_MAX_ATTEMPTS=2
OPENAI_RETRY_DELAYS_MS=750
```

Model dapat diganti melalui `.env` tanpa mengubah flow deterministic SIRKEL.

## BinderByte

```env
BINDERBYTE_API_KEY=
BINDERBYTE_BASE_URL=https://api.binderbyte.com
```

BinderByte bersifat opsional. Data wilayah bawaan Surabaya tetap tersedia.

## SIRKEL

```env
KTP_RETENTION_DAYS=30
SIRKEL_DEFAULT_PICKUP_RADIUS_KM=10
```

---

# Akun Demo

Seeder menyediakan akun untuk pengujian lokal.

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@sirkel.test` | `password123` |
| Warga | `warga@sirkel.test` | `password123` |
| Mitra Repair | `repair@sirkel.test` | `password123` |
| Mitra Recovery | `recovery@sirkel.test` | `password123` |
| Mitra Donation | `donation@sirkel.test` | `password123` |

`DemoSeeder` juga membuat beberapa mitra tambahan di berbagai wilayah Surabaya untuk menguji matching, radius, repair, recovery, donation/reuse, special handling, collection, dan pickup.

> Akun demo hanya untuk development/demo. Jangan gunakan password tersebut pada akun production.

---

# Automated Test

Jalankan seluruh test:

```bash
php artisan test
```

Atau test tertentu:

```bash
php artisan test --filter=NamaTest
```

Test environment menggunakan mailer in-memory sehingga PHPUnit tidak membutuhkan koneksi SMTP eksternal.

Sebelum commit besar disarankan menjalankan:

```bash
php artisan optimize:clear
npm run build
php artisan test
```

---

# Deployment Production

## Checklist Umum

Sebelum deploy:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-production.com
```

Kemudian:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Pastikan:

- `.env` production tidak pernah masuk Git;
- `storage/` dan `bootstrap/cache/` writable;
- document root mengarah ke folder `public`;
- HTTPS aktif;
- queue worker dijalankan jika menggunakan database queue secara asynchronous;
- scheduler/cron disiapkan jika ada command terjadwal yang digunakan;
- permission private identity/KTP tidak dapat diakses publik.

---

# Deployment Hostinger Shared Hosting

Struktur yang direkomendasikan ketika domain memiliki `public_html` tetap:

```text
sirkel.awicode.com/
├── app/                 ← source Laravel dari Git
└── public_html/         ← hanya isi folder public yang diakses web
```

Jangan menaruh keseluruhan source Laravel di `public_html`.

Contoh deploy pertama dari SSH, dijalankan dari root domain:

```bash
git clone https://github.com/AzizWira/sirkel.git app
cd app
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Isi `.env` production, lalu:

```bash
php artisan migrate --force
npm ci
npm run build
php artisan optimize:clear
```

Salin public files ke document root tanpa menyalin `index.php` default:

```bash
cd ..
rsync -a --delete --exclude=index.php app/public/ public_html/
```

Buat `public_html/index.php` yang menunjuk ke source Laravel di folder `app`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../app/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/../app/bootstrap/app.php';

$app->handleRequest(Request::capture());
```

Kemudian buat link storage ke document root:

```bash
rm -rf public_html/storage
ln -s ../app/storage/app/public public_html/storage
```

Untuk update berikutnya:

```bash
cd app
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
cd ..
rsync -a --delete --exclude=index.php app/public/ public_html/
```

Setelah deploy, cek minimal:

```text
/
/login
/app
/partner
/admin
```

sesuai role dan status autentikasi.

---

# Queue di Production

Default project menggunakan database queue.

Jika hosting mendukung worker long-running:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120
```

Jika shared hosting tidak mendukung worker permanen, gunakan cron yang sesuai kemampuan hosting, misalnya menjalankan queue secara periodik dengan konfigurasi yang aman untuk environment tersebut.

---

# Security & Privacy

Prinsip yang diterapkan:

- password di-hash melalui Laravel;
- CSRF protection pada form mutasi;
- role middleware untuk Warga, Mitra, dan Admin;
- email verification;
- private KTP storage;
- route-bound resource menggunakan opaque public ID pada resource utama;
- public passport tidak memuat PII;
- location detail dibatasi sesuai flow;
- chain-of-custody dicatat;
- audit log untuk aksi administratif;
- `.env`, vendor, node_modules, cache, dan private identity tidak masuk repository.

Untuk production, tetap lakukan audit security, backup database, rotasi secret, dan konfigurasi permission server sesuai environment deployment.

---

# Repository Hygiene

Repository hanya menyimpan source aplikasi, konfigurasi template, migration, seeder, automated test, dan dokumentasi proyek yang relevan. File rahasia, dependency hasil instalasi, build output, cache, serta artefak lokal tidak disimpan di Git.

Contoh file/direktori yang memang tidak masuk repository:

```text
.env
vendor/
node_modules/
public/build/
public/storage/
.phpunit.result.cache
```

Artefak kerja lokal lain dapat dikecualikan melalui Git local exclude agar aturan tersebut tidak menjadi bagian dari repository publik.

---

# License

Project ini menggunakan license **proprietary** sebagaimana didefinisikan pada `composer.json`.

Penggunaan, distribusi, dan modifikasi mengikuti izin pemilik project.

---

## Repository

GitHub: https://github.com/AzizWira/sirkel
