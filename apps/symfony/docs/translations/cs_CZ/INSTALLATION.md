# Populorum — Instalace a aktualizace

🇨🇿 **Čeština · cs_CZ**

> Populorum je fiktivní simulace doplňující stávající instituce. Hlasy nemají právní účinky. Nevkládejte skutečné osobní údaje ani produkční tajné údaje.

## Požadavky

Je vyžadováno PHP 8.4 nebo novější, Composer a SQLite. V Arch Linuxu nainstalujte balíčky pomocí pacman. Níže uvedená rozšíření ověřte příkazem `php -m`; v `/etc/php/php.ini` zapněte jen potřebné moduly, které již nejsou zakompilovány v PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Archiv z GitHubu neobsahuje `vendor/`. `composer install` nainstaluje verze určené v `composer.lock` a vytvoří `vendor/autoload.php`. Pro běžnou instalaci nepoužívejte `composer update`.

## První instalace

Rozbalte archiv a otevřete terminál v kořenovém adresáři repozitáře. Pokud již jste v `apps/symfony`, příkaz `cd` neopakujte.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Operátory `&&` při chybě zastaví posloupnost: před pokračováním chybu opravte. Otevřete http://127.0.0.1:5412/; Ctrl+C zastaví místní server. Inicializace vytvoří `.env.local`, pouze pokud neexistuje. Aktivní databáze je `var/populorum.sqlite`. Cookies profileru vyžadují HTTPS i při ručním místním testu.

## Aktualizace

Před nahrazením zdrojů zastavte server a zálohujte databázi, soubory a klíče. Zachovejte `.env.local`, `.guardian-shield/` a `var/`. Aktivní databázi nikdy nenahrazujte souborem `data/populorum-demo.sqlite`. V adresáři `apps/symfony` spusťte:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Po úspěšném dokončení všech kroků znovu spusťte místní server:

```bash
php -S 127.0.0.1:5412 -t public
```

## Řešení problémů

Pokud chybí `vendor/autoload.php`, zastavte server a v `apps/symfony` spusťte `composer install --no-interaction --prefer-dist`. Při chybě opravte požadovanou verzi PHP nebo chybějící rozšíření, než znovu spustíte příkazy Symfony. Nepoužívejte `--ignore-platform-reqs`. Při hostování musí DocumentRoot směřovat na `apps/symfony/public/`; použijte PHP-FPM, Apache nebo Nginx a HTTPS. Vestavěný server PHP je pouze pro místní testy.

## Referenční dokumentace (francouzsky)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
