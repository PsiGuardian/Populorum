# Populorum — Inštalácia a aktualizácia

🇸🇰 **Slovenčina · sk_SK**

> Populorum je fiktívna simulácia dopĺňajúca existujúce inštitúcie. Hlasy nemajú právne účinky. Nevkladajte skutočné osobné údaje ani produkčné tajné údaje.

## Požiadavky

Vyžaduje sa PHP 8.4 alebo novšie, Composer a SQLite. V Arch Linuxe nainštalujte balíky pomocou pacman. Nižšie uvedené rozšírenia overte príkazom `php -m`; v `/etc/php/php.ini` zapnite iba potrebné moduly, ktoré už nie sú zakompilované v PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Archív z GitHubu neobsahuje `vendor/`. `composer install` nainštaluje verzie určené v `composer.lock` a vytvorí `vendor/autoload.php`. Pri bežnej inštalácii nepoužívajte `composer update`.

## Prvá inštalácia

Rozbaľte archív a otvorte terminál v koreňovom adresári repozitára. Ak už ste v `apps/symfony`, príkaz `cd` neopakujte.

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

Operátory `&&` pri chybe zastavia postup: pred pokračovaním chybu opravte. Otvorte http://127.0.0.1:5412/; Ctrl+C zastaví lokálny server. Inicializácia vytvorí `.env.local`, iba ak neexistuje. Aktívna databáza je `var/populorum.sqlite`. Cookies profilera vyžadujú HTTPS aj pri ručnom lokálnom teste.

## Aktualizácia

Pred nahradením zdrojov zastavte server a zálohujte databázu, súbory a kľúče. Zachovajte `.env.local`, `.guardian-shield/` a `var/`. Aktívnu databázu nikdy nenahrádzajte súborom `data/populorum-demo.sqlite`. V adresári `apps/symfony` spustite:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Po úspešnom dokončení všetkých krokov znova spustite lokálny server:

```bash
php -S 127.0.0.1:5412 -t public
```

## Riešenie problémov

Ak chýba `vendor/autoload.php`, zastavte server a v `apps/symfony` spustite `composer install --no-interaction --prefer-dist`. Pri chybe opravte požadovanú verziu PHP alebo chýbajúce rozšírenia pred opätovným spustením príkazov Symfony. Nepoužívajte `--ignore-platform-reqs`. Pri hostovaní musí DocumentRoot smerovať na `apps/symfony/public/`; použite PHP-FPM, Apache alebo Nginx a HTTPS. Vstavaný server PHP slúži iba na lokálne testy.

## Referenčná dokumentácia (francúzsky)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
