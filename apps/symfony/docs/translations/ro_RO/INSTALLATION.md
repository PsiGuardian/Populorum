# Populorum — Instalare și actualizare

🇷🇴 **Română · ro_RO**

> Populorum este o simulare fictivă care completează instituțiile existente. Voturile nu au efecte juridice. Nu introduceți date personale reale sau secrete ale mediului de producție.

## Cerințe

Sunt necesare PHP 8.4 sau mai nou, Composer și SQLite. Pe Arch Linux, instalați pachetele cu pacman. Verificați extensiile de mai jos cu `php -m`; activați în `/etc/php/php.ini` doar modulele necesare care nu sunt deja compilate în PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Arhiva GitHub nu include `vendor/`. `composer install` instalează versiunile fixate în `composer.lock` și creează `vendor/autoload.php`. Nu utilizați `composer update` pentru o instalare obișnuită.

## Prima instalare

Extrageți arhiva și deschideți un terminal în rădăcina depozitului. Dacă sunteți deja în `apps/symfony`, nu repetați comanda `cd`.

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

Operatorii `&&` opresc secvența dacă apare o eroare: remediați-o înainte de a continua. Deschideți http://127.0.0.1:5412/; Ctrl+C oprește serverul local. Inițializarea creează `.env.local` numai dacă fișierul nu există. Baza activă este `var/populorum.sqlite`. Cookie-urile profilerului necesită HTTPS, inclusiv pentru un test manual local.

## Actualizare

Opriți serverul și salvați baza de date, fișierele și cheile înainte de a înlocui sursele. Păstrați `.env.local`, `.guardian-shield/` și `var/`. Nu înlocuiți niciodată o bază activă cu `data/populorum-demo.sqlite`. Din `apps/symfony`, executați:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

După finalizarea cu succes a tuturor pașilor, reporniți serverul local:

```bash
php -S 127.0.0.1:5412 -t public
```

## Depanare

Dacă lipsește `vendor/autoload.php`, opriți serverul și rulați `composer install --no-interaction --prefer-dist` din `apps/symfony`. Dacă instalarea eșuează, corectați versiunea PHP sau extensiile indicate înainte de a relua comenzile Symfony. Nu utilizați `--ignore-platform-reqs`. Pentru găzduire, DocumentRoot trebuie să fie `apps/symfony/public/`, cu PHP-FPM, Apache sau Nginx și HTTPS; serverul PHP integrat este numai pentru teste locale.

## Documentație de referință (franceză)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
