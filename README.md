# Traz Cloth

Toko kaos **pre-order limited drop** berbasis Laravel + Inertia React. Setiap kampanye punya kuota global; kampanye otomatis tutup saat kuota habis (tanpa deadline). Pembayaran memakai skema DP 50% dengan tenggat pelunasan 48 jam, pengiriman lewat Biteship, notifikasi WhatsApp (Fonnte) dan email transaksional.

Panduan keputusan bisnis lengkap ada di [`PLANNING.md`](PLANNING.md); aturan kerja untuk kontributor/agen ada di [`AGENTS.md`](AGENTS.md).

---

## 1. Stack

| Lapisan | Teknologi |
|---|---|
| Backend | Laravel 13, PHP 8.4+ |
| Frontend | Inertia v3, React + TypeScript, Vite, Tailwind CSS |
| Routing FE | Laravel Wayfinder (generate `resources/js/routes` & `actions`) |
| Auth | Laravel Fortify + OTP WhatsApp, guard `admin` terpisah |
| Database | PostgreSQL 17 (produksi), SQLite (opsional untuk lokal/test) |
| Queue & cache | Driver `database` (tabel `jobs`, `cache`) |
| Pembayaran | Midtrans Snap (DP + pelunasan sebagai transaksi terpisah) |
| Pengiriman | Biteship (rates, booking, webhook status) |
| Notifikasi | Fonnte (WhatsApp) + SMTP (email transaksional) |
| Deployment | Docker multi-stage + Docker Compose, cocok untuk Dokploy |

---

## 2. Prasyarat

- **PHP 8.4+** (disarankan 8.4.1+ — `composer.lock` mengunci Symfony 8.1 yang membutuhkan PHP >= 8.4.1)
- Ekstensi PHP: `pdo_pgsql`/`pgsql`, `bcmath`, `mbstring`, `openssl`, `opcache`
- **Composer 2**
- **Node.js 22** + **pnpm** (`corepack enable`)
- **PostgreSQL 17** (atau SQLite untuk mencoba cepat)

---

## 3. Menjalankan di lokal

### 3.1 Install dependency

```bash
git clone git@github.com:ryansutrisno/traz-cloth.git
cd traz-cloth

composer install
cp .env.example .env
php artisan key:generate
```

### 3.2 Siapkan database

**Opsi cepat — SQLite:**

```bash
# .env
# DB_CONNECTION=sqlite

touch database/database.sqlite
php artisan migrate --seed
```

**Opsi PostgreSQL (disarankan, sama seperti produksi):**

```bash
# .env
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=traz_cloth
# DB_USERNAME=traz_cloth
# DB_PASSWORD=...

php artisan migrate --seed
```

### 3.3 Storage, Wayfinder, dan frontend

```bash
php artisan storage:link                 # gambar kampanye & galeri
php artisan wayfinder:generate --with-form   # helper route/action untuk TypeScript

corepack enable
pnpm install
```

> `--with-form` wajib. Tanpa flag itu, helper `.form()` tidak dibuat dan `tsc` akan gagal dengan error `Property 'form' does not exist`.

### 3.4 Jalankan

```bash
composer dev
```

Perintah itu menjalankan empat proses sekaligus: `php artisan serve`, `queue:listen`, `pail` (log), dan `pnpm run dev` (Vite). Aplikasi tersedia di <http://localhost:8000>.

Kalau ingin manual, jalankan di terminal terpisah:

```bash
php artisan serve
php artisan queue:work
pnpm run dev
```

### 3.5 Akun demo (hasil `--seed`)

| Peran | URL login | Email | Password |
|---|---|---|---|
| Admin | `/admin/login` | `admin@traz.test` | `password123` |
| Operator | `/admin/login` | `operator@traz.test` | `password123` |
| Buyer | `/login` | `buyer@traz.test` | `password123` |

Buyer demo sudah punya nomor WhatsApp terverifikasi dan satu alamat pengiriman.

---

## 4. Environment variables penting

Semua variabel lengkap ada di `.env.example` (lokal) dan `.env.production.example` (produksi).

| Variabel | Keterangan |
|---|---|
| `APP_URL` | Wajib benar; dipakai untuk link pelunasan di WhatsApp/email |
| `DB_*` | PostgreSQL di produksi, SQLite untuk lokal cepat |
| `QUEUE_CONNECTION=database` | Job WA/email/webhook lewat queue — worker wajib jalan |
| `CACHE_STORE=database` | Dipakai juga untuk heartbeat scheduler |
| `MAIL_MAILER` | Default `log` (email hanya ditulis ke log). Isi SMTP di produksi |
| `WHATSAPP_DRIVER=fonnte`, `FONNTE_TOKEN`, `FONNTE_SENDER_ID` | Pengiriman OTP & notifikasi WhatsApp |
| `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION` | Pembayaran. **Kosong = dev mode**: token `dev-*` dan route pembayaran simulasi aktif |
| `BITESHIP_API_KEY`, `BITESHIP_ORIGIN_*`, `BITESHIP_COURIERS` | Cek ongkir, booking kurir, dan data pengirim di label |
| `BITESHIP_WEBHOOK_SIGNATURE_KEY`, `BITESHIP_WEBHOOK_SIGNATURE_SECRET` | Validasi webhook Biteship |
| `LOG_STACK`, `LOG_SLACK_WEBHOOK_URL` | Alert error ke Slack: `LOG_STACK=slack,daily` setelah URL diisi |

---

## 5. Testing & quality gate

```bash
php artisan test --compact                      # seluruh suite (PHPUnit)
php artisan test --compact tests/Feature/...    # satu file
vendor/bin/pint --dirty --format agent          # format PHP yang berubah
vendor/bin/phpstan analyse --no-progress --memory-limit=1G   # level 7, harus 0 error
composer ci:check                               # lint + format + tsc + test (sama seperti CI)
```

Frontend:

```bash
npm run lint:check      # eslint
npm run format:check    # prettier
npm run types:check     # tsc --noEmit
npm run build           # vite build
```

CI (`.github/workflows/tests.yml`) berjalan di **PHP 8.4**, menjalankan `composer setup`, lalu **PHPStan (blocking)** dan `composer ci:check`.

---

## 6. Operasional

| Command | Fungsi |
|---|---|
| `php artisan app:health-check` | Cek DB, heartbeat scheduler, backlog queue, `failed_jobs` 24 jam, storage, dan konfigurasi produksi. Exit `1` bila ada FAIL |
| `php artisan app:health-check --strict` | WARN (backlog/failed jobs) juga dianggap gagal |
| `php artisan app:heartbeat` | Menulis heartbeat scheduler (dijadwalkan tiap menit) |
| `php artisan app:reconcile-campaigns` | Audit **read-only** `sold_count` vs riwayat order, kuota terlampaui, stok negatif. Exit `1` bila ada temuan |
| `php artisan app:db-backup` | Backup PostgreSQL (`pg_dump` format custom) + retensi otomatis, dijadwalkan 02:00 |

Scheduler (`routes/console.php`):

| Command | Jadwal |
|---|---|
| `app:heartbeat` | tiap menit |
| `orders:auto-cancel` | tiap menit |
| `orders:payment-reminders` | tiap menit |
| `campaigns:check-sold-out` | tiap menit |
| `midtrans:sync-payments` | tiap 15 menit |
| `biteship:sync-status` | tiap 15 menit |
| `app:db-backup` | harian 02:00 |

Detail keputusan operasional ada di `PLANNING.md` §17.

---

## 7. Deployment

### 7.1 Docker Compose (VPS / lokal produksi)

```bash
cp .env.production.example .env      # isi APP_KEY, APP_URL, DB, Midtrans, Biteship, Fonnte, SMTP
docker compose up -d --build

docker compose exec app php artisan storage:link
docker compose exec app php artisan app:health-check
```

Service yang berjalan:

| Service | Peran |
|---|---|
| `app` | PHP-FPM (image multi-stage: composer vendor → wayfinder → vite build → runtime) |
| `nginx` | Reverse proxy port 80, healthcheck `/health` |
| `worker` | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600` |
| `scheduler` | `php artisan schedule:work` |
| `db` | PostgreSQL 17, data di volume `pgdata` |

Entrypoint container `app` otomatis menjalankan `php artisan migrate --force`, lalu `config:cache` + `route:cache` bila `APP_ENV=production`.

> **Catatan volume `public`:** direktori `public` dipakai bersama `app` dan `nginx` lewat named volume. Volume hanya terisi dari image saat pertama kali dibuat; setelah `--build` ulang, aset baru bisa tertinggal. Kalau tampilan terlihat memakai aset lama, hapus volume publik lalu jalankan ulang (ada downtime singkat):
>
> ```bash
> docker compose down
> docker volume rm "$(basename "$PWD")_app_public"
> docker compose up -d
> ```

### 7.2 Dokploy / VPS manual

1. **Build** image dari `Dockerfile` (sudah multi-stage, tidak butuh PHP/Node di host).
2. **Environment**: salin `.env.production.example`, isi minimal `APP_KEY`, `APP_URL` (https), `APP_ENV=production`, `APP_DEBUG=false`, kredensial DB, Midtrans, Biteship, Fonnte, SMTP.
3. **Proses** yang harus hidup: web (php-fpm + nginx), `php artisan queue:work`, dan `php artisan schedule:work`. Jangan hanya menjalankan web — notifikasi, auto-cancel, dan sinkronisasi status akan berhenti.
4. **Volume**: mount `storage/` (persisten) dan `public/` (dibagi ke nginx).
5. **Healthcheck**: arahkan ke `php artisan app:health-check` (exit code 0/1). Route `GET /up` tersedia untuk liveness sederhana.
6. **Webhook** didaftarkan di dashboard masing-masing penyedia:
   - Midtrans → `https://domain-kamu/webhook/midtrans`
   - Biteship → `https://domain-kamu/webhook/biteship` (isi signature key/secret yang sama dengan `.env`)
7. **Sekali setelah deploy**: `php artisan storage:link`.
8. **Backup**: pastikan `app:db-backup` terjadwal (sudah otomatis lewat scheduler) dan volume backup ikut tersimpan. Restore manual: `pg_restore --clean --if-exists -d <db> <file.dump>`.

### 7.3 Checklist go-live

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` https
- [ ] `APP_KEY` terisi, `SESSION_SECURE_COOKIE=true`
- [ ] Midtrans production key + `MIDTRANS_IS_PRODUCTION=true`
- [ ] Biteship live key + origin address lengkap (dipakai di label)
- [ ] Fonnte token + sender terisi
- [ ] `MAIL_MAILER=smtp` + `MAIL_FROM_ADDRESS` resmi
- [ ] `LOG_SLACK_WEBHOOK_URL` + `LOG_STACK=slack,daily`
- [ ] Worker queue dan scheduler aktif
- [ ] `php artisan storage:link` dijalankan
- [ ] `app:health-check` hijau dan `app:reconcile-campaigns` bersih
- [ ] Webhook Midtrans & Biteship terdaftar dan signature cocok

---

## 8. Struktur direktori singkat

```
app/
  Console/Commands/     # auto-cancel, reminder, sync, backup, health-check, reconcile
  Enums/                # status order, peran admin, tipe kupon, dsb.
  Http/Controllers/     # Storefront/, Admin/, Webhook/, Settings/
  Jobs/                 # webhook processor, WhatsApp, email
  Models/               # Campaign, Order, Payment, Coupon, CampaignImage, ...
  Services/             # OrderService, CartService, CouponService, Biteship, Midtrans, OTP
database/
  migrations/           # skema + index performa
  factories/ seeders/   # data demo
resources/
  js/pages/             # halaman Inertia (storefront, admin, auth, settings)
  js/layouts/           # storefront & admin shell
  views/mail/           # template email transaksional
  views/labels/         # label pengiriman 105x203 mm (HTML print)
routes/
  web.php admin.php webhook.php console.php
tests/Feature tests/Unit
```

---

## 9. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| `ViteException: Unable to locate file in Vite manifest` | Aset belum dibuild → `pnpm run build` atau jalankan `composer dev` |
| `tsc`: `Property 'form' does not exist` | Wayfinder digenerate tanpa form → `php artisan wayfinder:generate --with-form` |
| Gambar kampanye 404 | `php artisan storage:link` belum dijalankan |
| Email tidak terkirim | `MAIL_MAILER=log` (default) atau worker queue mati |
| WhatsApp/OTP tidak masuk | `FONNTE_TOKEN` kosong (driver jadi no-op) atau worker mati |
| `app:health-check` FAIL di heartbeat | Scheduler tidak berjalan; jalankan `php artisan schedule:work` |
| Job menumpuk di tabel `jobs` | Worker queue tidak jalan atau crash; cek `failed_jobs` dan log |
| Status order tidak berubah setelah bayar | Webhook Midtrans belum terdaftar / signature salah; jalankan `midtrans:sync-payments` manual |

---

## 10. Lisensi

Proyek privat milik Traz Cloth. Tidak untuk didistribusikan ulang tanpa izin.
