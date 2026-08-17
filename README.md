# RachaqaKost Internal

Sistem operasional kos berbasis Laravel 12, disiapkan untuk Railway + Supabase PostgreSQL.

## Fitur

- Dashboard pendapatan, pengeluaran, margin, okupansi, jatuh tempo, dan maintenance.
- Kategori kamar dinamis beserta warna dan harga bulanan.
- Pemetaan kamar ke kategori serta status kosong/terisi/maintenance.
- Check-in, check-out, penghuni aktif, dan histori penghuni.
- Pembayaran dengan periode otomatis non-editable yang memajukan jatuh tempo 1–24 bulan.
- Input Rupiah berformat separator ribuan serta normalisasi ulang di backend.
- Follow-up WhatsApp dari dashboard dan data penghuni dengan template dinamis yang dapat diatur Owner.
- Master kategori pengeluaran yang dapat ditambah, diubah, dan dihapus dari panel Owner.
- Filter rentang tanggal untuk KPI pendapatan/pengeluaran serta grafik arus kas interaktif.
- Pengeluaran operasional dan biaya maintenance otomatis.
- Tiket dan histori maintenance lengkap tanggal, biaya, serta pencatat.
- Role Owner dan Penjaga; hanya Owner yang mengatur harga, kategori, dan user.
- UI responsif desktop/mobile tanpa frontend build step.

## Infrastruktur

- **Database live:** Supabase project `Ranneciva Project` (`ezljagziqtrvfhpbdbnq`), region Singapore.
- **App host:** Railway menggunakan `Dockerfile`.
- **Koneksi:** Supabase Shared Pooler, Session mode port `5432` agar kompatibel IPv4 Railway.
- **Security:** RLS aktif, akses `anon`/`authenticated` dicabut, aplikasi hanya mengakses DB melalui backend Laravel.
- **Session/cache:** disimpan di PostgreSQL agar aman saat container restart.

## Deploy Railway

1. Push isi folder ini ke repository GitHub baru.
2. Railway → New Project → Deploy from GitHub repo.
3. Isi variables berikut:

```dotenv
APP_NAME=RachaqaKost
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMAIN-RAILWAY
APP_KEY=base64:HASIL_PHP_ARTISAN_KEY_GENERATE
APP_LOCALE=id
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-southeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.ezljagziqtrvfhpbdbnq
DB_PASSWORD=PASSWORD_DATABASE_SUPABASE
DB_SSLMODE=require
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
INITIAL_OWNER_NAME=Avicenna Rabama
INITIAL_OWNER_EMAIL=EMAIL_LOGIN_OWNER
INITIAL_OWNER_PASSWORD=PASSWORD_OWNER_MINIMAL_10_KARAKTER
```

4. Deploy. Entrypoint otomatis menjalankan migration, bootstrap Owner secara idempotent, lalu cache config/routes/views.
5. Health check tersedia di `/up`.

`DB_PASSWORD`, `APP_KEY`, dan password Owner tidak boleh dimasukkan ke repository.

## Menjalankan lokal

Butuh PHP 8.2+, Composer, dan ekstensi SQLite. Ubah `.env` ke `DB_CONNECTION=sqlite`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, dan `SESSION_SECURE_COOKIE=false`, lalu jalankan:

```bash
composer install
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan serve
```
