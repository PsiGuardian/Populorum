# Populorum — Суулгах ба шинэчлэх

🇲🇳 **Монгол · mn_MN**

> Populorum нь одоо байгаа байгууллагуудыг дэмжих зорилготой зохиомол симуляци юм. Санал хураалт нь хууль зүйн үр дагаваргүй. Бодит хувийн мэдээлэл болон ашиглалтын орчны нууц мэдээлэл бүү оруул.

## Шаардлага

PHP 8.4 эсвэл түүнээс шинэ хувилбар, Composer болон SQLite шаардлагатай. Arch Linux дээр багцуудыг pacman-аар суулгана. Доорх өргөтгөлүүдийг `php -m` командаар шалга; `/etc/php/php.ini` дотор зөвхөн шаардлагатай бөгөөд PHP-д аль хэдийн суулгагдаагүй модулиудыг идэвхжүүл.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

GitHub-ийн архивт `vendor/` байхгүй. `composer install` нь `composer.lock` дотор тогтоосон хувилбаруудыг суулгаж, `vendor/autoload.php` файлыг үүсгэнэ. Ердийн суулгалтад `composer update` бүү ашигла.

## Анхны суулгалт

Архивыг задлаад репозиторийн үндсэн хавтсанд терминал нээ. Хэрэв `apps/symfony` дотор байгаа бол `cd` командыг алгас.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Аль нэг алхам алдаатай бол `&&` дарааллыг зогсооно: үргэлжлүүлэхийн өмнө алдааг зас. http://127.0.0.1:5412/ хаягийг нээ; Ctrl+C нь локал серверийг зогсооно. Эхлүүлэх үйлдэл нь `.env.local` байхгүй үед л үүсгэнэ. Идэвхтэй өгөгдлийн сан нь `var/populorum.sqlite`. Профайлерын cookie нь локал гараар хийх туршилтад ч HTTPS шаарддаг.

## Шинэчлэх

Эх файлуудыг солихын өмнө серверийг зогсоож, өгөгдлийн сан, файлууд болон түлхүүрүүдээ нөөцөл. `.env.local`, `.guardian-shield/` болон `var/`-г хадгал. Идэвхтэй өгөгдлийн санг `data/populorum-demo.sqlite` файлаар хэзээ ч бүү соль. `apps/symfony` дотроос ажиллуул:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Бүх алхам амжилттай дууссаны дараа локал серверийг дахин ажиллуул:

```bash
php -S 127.0.0.1:5412 -t public
```

## Алдаа засах

Хэрэв `vendor/autoload.php` байхгүй бол серверийг зогсоож, `apps/symfony` дотроос `composer install --no-interaction --prefer-dist` ажиллуул. Алдаа гарвал Symfony командуудыг дахин ажиллуулахын өмнө заасан PHP хувилбар эсвэл дутуу өргөтгөлүүдийг зас. `--ignore-platform-reqs` бүү ашигла. Хостингийн DocumentRoot нь `apps/symfony/public/` байх ёстой; PHP-FPM, Apache эсвэл Nginx болон HTTPS ашигла. PHP-ийн суурилуулсан сервер нь зөвхөн локал туршилтад зориулагдсан.

## Лавлах баримт бичиг (франц хэлээр)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
