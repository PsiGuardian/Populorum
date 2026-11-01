# Populorum — Installation and updates

🇬🇧 **English (UK) · en_GB**

> Populorum is a fictional simulation that complements existing institutions. Votes have no legal effect. Do not enter real personal data or production secrets.

## Requirements

PHP 8.4 or later, Composer and SQLite are required. On Arch Linux, install the packages with pacman. Check the extensions below with `php -m`; enable modules in `/etc/php/php.ini` only when needed and not already compiled into PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

The GitHub archive does not include `vendor/`. `composer install` installs the versions pinned in `composer.lock` and creates `vendor/autoload.php`. Do not use `composer update` for a normal installation.

## First installation

Extract the archive and open a terminal at the repository root. If you are already in `apps/symfony`, do not repeat the `cd` command.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

The `&&` operators stop the sequence if a step fails: fix the error before continuing. Open http://127.0.0.1:5412/; Ctrl+C stops the local server. Initialisation creates `.env.local` only if it does not exist. The active database is `var/populorum.sqlite`. Profiler cookies require HTTPS, even for a manual local test.

## Updating

Stop the server and back up the database, files and keys before replacing the source files. Preserve `.env.local`, `.guardian-shield/` and `var/`. Never replace an active database with `data/populorum-demo.sqlite`. From `apps/symfony`, run:

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

After all steps succeed, restart the local server:

```bash
php -S 127.0.0.1:5412 -t public
```

## Troubleshooting

If `vendor/autoload.php` is missing, stop the server and run `composer install --no-interaction --prefer-dist` from `apps/symfony`. If installation fails, fix the reported PHP version or missing extensions before running Symfony commands again. Do not use `--ignore-platform-reqs`. For hosting, set the DocumentRoot to `apps/symfony/public/` and use PHP-FPM, Apache or Nginx, and HTTPS; the built-in PHP server is for local testing only.

## Reference documentation (French)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
