# Idempotent webhook: contoh Laravel

[![tests](https://github.com/nextmend/idempotent-webhook-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/nextmend/idempotent-webhook-laravel/actions/workflows/tests.yml)

Contoh yang bisa dijalankan untuk artikel [Idempotent webhook: mencegah transaksi ganda](https://nextmend.dev/artikel/idempotent-webhook-mencegah-transaksi-ganda/?utm_source=github&utm_medium=readme&utm_campaign=artikel-idempotent).

Sebuah toko voucher game menerbitkan satu kode voucher untuk setiap order yang dibayar. Midtrans bisa mengirim notifikasi yang sama lebih dari sekali, dan mengirim ulang notifikasi kalau server tidak membalas 2xx (kecuali redirect `301`, `302`, dan `303`). Repo ini mereproduksi dua cara satu pembayaran berubah menjadi dua voucher di Laravel, lalu memperbaikinya:

1. Email gagal terkirim setelah voucher dibuat. Server membalas 500, dan retry dari Midtrans menerbitkan voucher kedua.
2. Notifikasi yang sama datang dua kali pada saat bersamaan. Keduanya membaca order yang masih `pending`, dan keduanya menerbitkan voucher.

Test duplikat biasa, dua notifikasi yang dikirim berurutan, tetap hijau. Karena itu bug ini lolos dari test.

## Isi

| File | Isi |
| --- | --- |
| `app/Http/Controllers/MidtransNotificationController.php` | Verifikasi `signature_key`, lalu terbitkan voucher untuk order yang dibayar |
| `database/migrations/` | Tabel `orders` dan `vouchers`; di tahap perbaikan ditambah unique index `vouchers.order_id` |
| `app/Mail/VoucherIssued.php` | Email berisi kode voucher; di tahap perbaikan dikirim lewat antrean |
| `routes/console.php` | `demo:notifikasi-dobel`: mengirim notifikasi yang sama dua kali pada saat bersamaan |
| `tests/Feature/MidtransNotificationTest.php` | Test notifikasi, retry, duplikat di tengah proses, dan unique index |
| `contoh/notifikasi-settlement.json` | Notifikasi contoh, ditandatangani dengan Server Key placeholder |

## Menjalankan

Butuh PHP 8.4+ dan Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

Test: `php artisan test`

### Demo dengan request paralel

Jalankan server dengan beberapa worker, supaya dua request bisa diproses bersamaan:

```bash
PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload
```

`PHP_CLI_SERVER_WORKERS` tidak didukung di Windows.

Di terminal lain:

```bash
php artisan demo:notifikasi-dobel --orders=50
```

Command ini membuat 50 order baru, mengirim notifikasi `settlement` yang sama dua kali sekaligus untuk setiap order, lalu menghitung order yang mendapat lebih dari satu voucher. Angkanya berubah setiap kali dijalankan.

Untuk mencobanya di MySQL, isi `DB_*` di `.env`, jalankan `php artisan migrate:fresh --seed`, lalu jalankan ulang `php artisan serve`. Server membaca konfigurasi database saat mulai.

## Langkah demi langkah

Setiap tahap punya tag. Jalankan `git checkout <tag>`, lalu `php artisan migrate:fresh --seed` (tag `3-perbaikan` menambah satu migration), `php artisan test`, dan demo di atas.

| Tag | Isi | `php artisan test` | `demo:notifikasi-dobel` |
| --- | --- | --- | --- |
| `1-bug` | Cek status `pending`, terbitkan voucher, kirim email, baru tandai `paid` | hijau, 3 test | sebagian besar order dobel |
| `2-test-merah` | Tiga test baru: retry setelah email gagal, duplikat di tengah proses, unique index | merah, 3 gagal | sebagian besar order dobel |
| `3-perbaikan` | Klaim order dengan `UPDATE` bersyarat dalam satu transaksi dengan voucher, unique index, email lewat antrean | hijau, 6 test | 0 order dobel |

## Catatan

- `MIDTRANS_SERVER_KEY` di `.env.example` hanya placeholder. Untuk mencoba dengan sandbox Midtrans, isi `.env` dengan Server Key sandbox Anda dan jangan commit file itu.
- Di tahap `3-perbaikan`, email masuk ke antrean `database`. Jalankan `php artisan queue:work` supaya email benar-benar dikirim (ke log, karena `MAIL_MAILER=log`).
- Ini contoh untuk belajar, bukan paket siap pakai. Sesuaikan status order dan efek sampingnya dengan aplikasi Anda.

Lisensi MIT. Dibuat oleh [Nextmend](https://nextmend.dev/?utm_source=github&utm_medium=readme&utm_campaign=artikel-idempotent).
