# Populorum — Eerste installatie

🇳🇱 **Nederlands · nl_NL**

> Populorum is een fictieve simulatie die bestaande instellingen aanvult. Stemmen hebben geen rechtsgevolgen. Voer geen echte persoonsgegevens of productiegeheimen in.

## Vereisten

PHP 8.4 of hoger, Composer en SQLite zijn vereist. Installeer op Arch Linux de pakketten met pacman. Controleer de onderstaande extensies met `php -m`; schakel in `/etc/php/php.ini` alleen benodigde modules in die niet al in PHP zijn ingebouwd.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Het GitHub-archief bevat geen `vendor/`. `composer install` installeert de versies uit `composer.lock` en maakt `vendor/autoload.php` aan. Gebruik geen `composer update` voor een normale installatie.

## Eerste installatie

Pak het archief uit en open een terminal in de hoofdmap van de repository. Als u al in `apps/symfony` bent, slaat u het commando `cd` over.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

De operatoren `&&` stoppen de reeks bij een fout: los die op voordat u verdergaat. Open http://127.0.0.1:5412/; Ctrl+C stopt de lokale server. De initialisatie maakt `.env.local` alleen aan als het bestand niet bestaat. De actieve database is `var/populorum.sqlite`. Profiler-cookies vereisen HTTPS, ook bij een handmatige lokale test.

## Problemen oplossen

Als `vendor/autoload.php` ontbreekt, stop dan de server en voer `composer install --no-interaction --prefer-dist` uit vanuit `apps/symfony`. Herstel bij een fout eerst de gemelde PHP-versie of ontbrekende extensies voordat u de Symfony-commando’s hervat. Gebruik geen `--ignore-platform-reqs`. Voor hosting moet de DocumentRoot `apps/symfony/public/` zijn, met PHP-FPM, Apache of Nginx en HTTPS; de ingebouwde PHP-server is alleen voor lokale tests.

## Referentiedocumentatie (Frans)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
