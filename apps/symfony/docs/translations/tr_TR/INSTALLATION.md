# Populorum — Kurulum ve güncelleme

🇹🇷 **Türkçe · tr_TR**

> Populorum, mevcut kurumları tamamlayan kurgusal bir simülasyondur. Oyların hukuki etkisi yoktur. Gerçek kişisel veriler veya üretim ortamına ait gizli bilgiler girmeyin.

## Gereksinimler

PHP 8.4 veya üzeri, Composer ve SQLite gereklidir. Arch Linux’ta paketleri pacman ile kurun. Aşağıdaki eklentileri `php -m` ile kontrol edin; `/etc/php/php.ini` içinde yalnızca gerekli olan ve PHP’ye zaten derlenmemiş modülleri etkinleştirin.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

GitHub arşivi `vendor/` içermez. `composer install`, `composer.lock` ile sabitlenen sürümleri kurar ve `vendor/autoload.php` dosyasını oluşturur. Normal kurulum için `composer update` kullanmayın.

## İlk kurulum

Arşivi açın ve deponun kök dizininde bir terminal açın. Zaten `apps/symfony` içindeyseniz `cd` komutunu tekrarlamayın.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

`&&` işleçleri bir hata oluştuğunda sırayı durdurur: devam etmeden önce hatayı giderin. http://127.0.0.1:5412/ adresini açın; Ctrl+C yerel sunucuyu durdurur. Başlatma işlemi `.env.local` dosyasını yalnızca mevcut değilse oluşturur. Etkin veritabanı `var/populorum.sqlite` dosyasıdır. Profiler çerezleri, elle yapılan yerel testlerde bile HTTPS gerektirir.

## Güncelleme

Kaynakları değiştirmeden önce sunucuyu durdurun; veritabanını, dosyaları ve anahtarları yedekleyin. `.env.local`, `.guardian-shield/` ve `var/` öğelerini koruyun. Etkin veritabanını asla `data/populorum-demo.sqlite` ile değiştirmeyin. `apps/symfony` içinde çalıştırın:

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Tüm adımlar başarıyla tamamlandıktan sonra yerel sunucuyu yeniden başlatın:

```bash
php -S 127.0.0.1:5412 -t public
```

## Sorun giderme

`vendor/autoload.php` eksikse sunucuyu durdurun ve `apps/symfony` içinde `composer install --no-interaction --prefer-dist` komutunu çalıştırın. Başarısız olursa Symfony komutlarını yeniden çalıştırmadan önce bildirilen PHP sürümünü veya eksik eklentileri düzeltin. `--ignore-platform-reqs` kullanmayın. Barındırma için DocumentRoot `apps/symfony/public/` olmalı; PHP-FPM, Apache veya Nginx ve HTTPS kullanılmalıdır. PHP’nin yerleşik sunucusu yalnızca yerel testler içindir.

## Başvuru belgeleri (Fransızca)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
