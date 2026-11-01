# Populorum — Instalacija i ažuriranje

🇭🇷 **Hrvatski · hr_HR**

> Populorum je fiktivna simulacija koja nadopunjuje postojeće institucije. Glasovi nemaju pravni učinak. Ne unosite stvarne osobne podatke ni tajne produkcijskog okruženja.

## Preduvjeti

Potrebni su PHP 8.4 ili noviji, Composer i SQLite. Na Arch Linuxu instalirajte pakete pomoću pacmana. Provjerite proširenja navedena u nastavku naredbom `php -m`; u `/etc/php/php.ini` omogućite samo potrebne module koji već nisu ugrađeni u PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Arhiva s GitHuba ne sadrži `vendor/`. `composer install` instalira verzije zadane u `composer.lock` i stvara `vendor/autoload.php`. Za uobičajenu instalaciju nemojte koristiti `composer update`.

## Prva instalacija

Raspakirajte arhivu i otvorite terminal u korijenskom direktoriju repozitorija. Ako ste već u `apps/symfony`, preskočite naredbu `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Operatori `&&` zaustavljaju niz pri pogrešci: ispravite je prije nastavka. Otvorite http://127.0.0.1:5412/; Ctrl+C zaustavlja lokalni poslužitelj. Inicijalizacija stvara `.env.local` samo ako datoteka ne postoji. Aktivna baza je `var/populorum.sqlite`. Kolačići profilera zahtijevaju HTTPS, čak i pri ručnom lokalnom testiranju.

## Ažuriranje

Prije zamjene izvornog koda zaustavite poslužitelj i sigurnosno kopirajte bazu, datoteke i ključeve. Sačuvajte `.env.local`, `.guardian-shield/` i `var/`. Aktivnu bazu nikada nemojte zamijeniti datotekom `data/populorum-demo.sqlite`. U `apps/symfony` pokrenite:

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Nakon uspješnog završetka svih koraka ponovno pokrenite lokalni poslužitelj:

```bash
php -S 127.0.0.1:5412 -t public
```

## Rješavanje problema

Ako nedostaje `vendor/autoload.php`, zaustavite poslužitelj i pokrenite `composer install --no-interaction --prefer-dist` iz `apps/symfony`. Ako instalacija ne uspije, ispravite navedenu verziju PHP-a ili proširenja prije ponovnog pokretanja naredbi Symfonyja. Nemojte koristiti `--ignore-platform-reqs`. Za hosting DocumentRoot mora biti `apps/symfony/public/`, uz PHP-FPM, Apache ili Nginx i HTTPS; ugrađeni PHP poslužitelj služi samo lokalnim testovima.

## Referentna dokumentacija (francuski)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
