# Populorum — Instalacja i aktualizacja

🇵🇱 **Polski · pl_PL**

> Populorum to fikcyjna symulacja uzupełniająca istniejące instytucje. Głosy nie wywołują skutków prawnych. Nie wprowadzaj prawdziwych danych osobowych ani sekretów środowiska produkcyjnego.

## Wymagania

Wymagane są PHP 8.4 lub nowsze, Composer i SQLite. W Arch Linux zainstaluj pakiety przez pacman. Sprawdź poniższe rozszerzenia poleceniem `php -m`; w `/etc/php/php.ini` włącz tylko potrzebne moduły, które nie są już wkompilowane w PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Archiwum GitHub nie zawiera `vendor/`. `composer install` instaluje wersje zapisane w `composer.lock` i tworzy `vendor/autoload.php`. Nie używaj `composer update` do zwykłej instalacji.

## Pierwsza instalacja

Rozpakuj archiwum i otwórz terminal w katalogu głównym repozytorium. Jeśli jesteś już w `apps/symfony`, pomiń polecenie `cd`.

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

Operatory `&&` zatrzymują sekwencję po błędzie: napraw go przed kontynuowaniem. Otwórz http://127.0.0.1:5412/; Ctrl+C zatrzymuje lokalny serwer. Inicjalizacja tworzy `.env.local` tylko wtedy, gdy plik nie istnieje. Aktywna baza to `var/populorum.sqlite`. Pliki cookie profilera wymagają HTTPS, także podczas ręcznego testu lokalnego.

## Aktualizacja

Zatrzymaj serwer i wykonaj kopię bazy, plików oraz kluczy przed zastąpieniem źródeł. Zachowaj `.env.local`, `.guardian-shield/` i `var/`. Nigdy nie zastępuj aktywnej bazy plikiem `data/populorum-demo.sqlite`. W katalogu `apps/symfony` uruchom:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Po pomyślnym zakończeniu wszystkich kroków uruchom ponownie lokalny serwer:

```bash
php -S 127.0.0.1:5412 -t public
```

## Rozwiązywanie problemów

Jeśli brakuje `vendor/autoload.php`, zatrzymaj serwer i uruchom `composer install --no-interaction --prefer-dist` w `apps/symfony`. W razie błędu popraw wskazaną wersję PHP lub brakujące rozszerzenia przed ponownym uruchomieniem poleceń Symfony. Nie używaj `--ignore-platform-reqs`. Przy hostingu DocumentRoot musi wskazywać `apps/symfony/public/`; używaj PHP-FPM, Apache lub Nginx i HTTPS. Wbudowany serwer PHP służy tylko do testów lokalnych.

## Dokumentacja referencyjna (francuski)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
