# Populorum — Pemasangan dan kemas kini

🇲🇾 **Bahasa Melayu · ms_MY**

> Populorum ialah simulasi fiksyen yang melengkapi institusi sedia ada. Undian tidak mempunyai kesan undang-undang. Jangan masukkan data peribadi sebenar atau rahsia persekitaran pengeluaran.

## Keperluan

PHP 8.4 atau lebih baharu, Composer dan SQLite diperlukan. Pada Arch Linux, pasang pakej menggunakan pacman. Semak sambungan di bawah dengan `php -m`; dayakan dalam `/etc/php/php.ini` hanya modul yang diperlukan dan belum dikompil ke dalam PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Arkib GitHub tidak menyertakan `vendor/`. `composer install` memasang versi yang dikunci dalam `composer.lock` dan mencipta `vendor/autoload.php`. Jangan gunakan `composer update` untuk pemasangan biasa.

## Pemasangan pertama

Ekstrak arkib dan buka terminal pada direktori akar repositori. Jika sudah berada dalam `apps/symfony`, jangan ulangi arahan `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Operator `&&` menghentikan turutan jika berlaku ralat: betulkan sebelum meneruskan. Buka http://127.0.0.1:5412/; Ctrl+C menghentikan pelayan setempat. Pengawalan mula mencipta `.env.local` hanya jika fail itu belum wujud. Pangkalan data aktif ialah `var/populorum.sqlite`. Kuki profiler memerlukan HTTPS, termasuk untuk ujian setempat secara manual.

## Kemas kini

Hentikan pelayan dan sandarkan pangkalan data, fail serta kunci sebelum menggantikan sumber. Kekalkan `.env.local`, `.guardian-shield/` dan `var/`. Jangan sekali-kali menggantikan pangkalan data aktif dengan `data/populorum-demo.sqlite`. Dari `apps/symfony`, jalankan:

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Selepas semua langkah berjaya, mulakan semula pelayan setempat:

```bash
php -S 127.0.0.1:5412 -t public
```

## Penyelesaian masalah

Jika `vendor/autoload.php` tiada, hentikan pelayan dan jalankan `composer install --no-interaction --prefer-dist` dari `apps/symfony`. Jika gagal, betulkan versi PHP atau sambungan yang dilaporkan sebelum menjalankan semula arahan Symfony. Jangan gunakan `--ignore-platform-reqs`. Untuk pengehosan, DocumentRoot mestilah `apps/symfony/public/`, dengan PHP-FPM, Apache atau Nginx dan HTTPS; pelayan terbina dalam PHP hanya untuk ujian setempat.

## Dokumentasi rujukan (bahasa Perancis)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
