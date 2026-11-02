# Populorum — Instalasi dan pembaruan

🇮🇩 **Bahasa Indonesia · id_ID**

> Populorum adalah simulasi fiktif yang melengkapi lembaga yang sudah ada. Suara tidak memiliki akibat hukum. Jangan masukkan data pribadi nyata atau rahasia lingkungan produksi.

## Persyaratan

Diperlukan PHP 8.4 atau lebih baru, Composer, dan SQLite. Di Arch Linux, instal paket menggunakan pacman. Periksa ekstensi di bawah dengan `php -m`; aktifkan di `/etc/php/php.ini` hanya modul yang diperlukan dan belum dikompilasi ke dalam PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Arsip GitHub tidak menyertakan `vendor/`. `composer install` menginstal versi yang dikunci dalam `composer.lock` dan membuat `vendor/autoload.php`. Jangan gunakan `composer update` untuk instalasi biasa.

## Instalasi pertama

Ekstrak arsip dan buka terminal di direktori akar repositori. Jika sudah berada di `apps/symfony`, jangan ulangi perintah `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Operator `&&` menghentikan urutan jika terjadi kesalahan: perbaiki sebelum melanjutkan. Buka http://127.0.0.1:5412/; Ctrl+C menghentikan server lokal. Inisialisasi membuat `.env.local` hanya jika belum ada. Basis data aktif adalah `var/populorum.sqlite`. Cookie profiler memerlukan HTTPS, termasuk untuk pengujian lokal manual.

## Pembaruan

Hentikan server dan cadangkan basis data, berkas, serta kunci sebelum mengganti sumber. Pertahankan `.env.local`, `.guardian-shield/`, dan `var/`. Jangan pernah mengganti basis data aktif dengan `data/populorum-demo.sqlite`. Dari `apps/symfony`, jalankan:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Setelah semua langkah berhasil, jalankan kembali server lokal:

```bash
php -S 127.0.0.1:5412 -t public
```

## Pemecahan masalah

Jika `vendor/autoload.php` tidak ditemukan, hentikan server dan jalankan `composer install --no-interaction --prefer-dist` dari `apps/symfony`. Jika gagal, perbaiki versi PHP atau ekstensi yang dilaporkan sebelum menjalankan kembali perintah Symfony. Jangan gunakan `--ignore-platform-reqs`. Untuk hosting, DocumentRoot harus menunjuk ke `apps/symfony/public/`, menggunakan PHP-FPM, Apache atau Nginx, dan HTTPS; server bawaan PHP hanya untuk pengujian lokal.

## Dokumentasi rujukan (bahasa Prancis)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
