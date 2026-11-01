# Populorum — Telepítés és frissítés

🇭🇺 **Magyar · hu_HU**

> A Populorum a meglévő intézményeket kiegészítő fiktív szimuláció. A szavazatoknak nincs joghatásuk. Ne adjon meg valódi személyes adatokat vagy éles rendszerhez tartozó titkokat.

## Előfeltételek

PHP 8.4 vagy újabb verzió, Composer és SQLite szükséges. Arch Linuxon a csomagokat a pacman segítségével telepítse. Az alábbi bővítményeket a `php -m` paranccsal ellenőrizze; az `/etc/php/php.ini` fájlban csak a szükséges, PHP-ba még nem beépített modulokat engedélyezze.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

A GitHub-archívum nem tartalmaz `vendor/` könyvtárat. A `composer install` a `composer.lock` által rögzített verziókat telepíti, és létrehozza a `vendor/autoload.php` fájlt. Normál telepítéshez ne használja a `composer update` parancsot.

## Első telepítés

Csomagolja ki az archívumot, és nyisson terminált a tároló gyökérkönyvtárában. Ha már az `apps/symfony` könyvtárban van, hagyja ki a `cd` parancsot.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

A `&&` operátorok hiba esetén megszakítják a parancssort: folytatás előtt javítsa a hibát. Nyissa meg a http://127.0.0.1:5412/ címet; a Ctrl+C leállítja a helyi szervert. Az inicializálás csak akkor hozza létre az `.env.local` fájlt, ha még nem létezik. Az aktív adatbázis: `var/populorum.sqlite`. A profiler sütijeihez HTTPS szükséges, kézi helyi teszt esetén is.

## Frissítés

A forrásfájlok cseréje előtt állítsa le a szervert, és készítsen biztonsági másolatot az adatbázisról, a fájlokról és a kulcsokról. Őrizze meg az `.env.local`, `.guardian-shield/` és `var/` tartalmát. Az aktív adatbázist soha ne írja felül a `data/populorum-demo.sqlite` fájllal. Az `apps/symfony` könyvtárban futtassa:

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Az összes lépés sikeres befejezése után indítsa újra a helyi szervert:

```bash
php -S 127.0.0.1:5412 -t public
```

## Hibaelhárítás

Ha hiányzik a `vendor/autoload.php`, állítsa le a szervert, majd futtassa a `composer install --no-interaction --prefer-dist` parancsot az `apps/symfony` könyvtárban. Hiba esetén javítsa a jelzett PHP-verziót vagy hiányzó bővítményeket, mielőtt újra futtatná a Symfony parancsait. Ne használja a `--ignore-platform-reqs` kapcsolót. Éles elhelyezéskor a DocumentRoot az `apps/symfony/public/` legyen, PHP-FPM, Apache vagy Nginx és HTTPS használatával; a beépített PHP-szerver csak helyi tesztekre való.

## Referenciadokumentáció (francia)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
