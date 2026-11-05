# Populorum — Erstinstallation

🇩🇪 **Deutsch · de_DE**

> Populorum ist eine fiktive Simulation als Ergänzung zu bestehenden Institutionen. Abstimmungen haben keine rechtliche Wirkung. Keine echten personenbezogenen Daten oder Produktionsgeheimnisse eingeben.

## Voraussetzungen

Erforderlich sind PHP 8.4 oder neuer, Composer und SQLite. Unter Arch Linux die Pakete mit pacman installieren. Die unten aufgeführten Erweiterungen mit `php -m` prüfen; in `/etc/php/php.ini` nur benötigte Module aktivieren, die nicht bereits in PHP einkompiliert sind.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Das GitHub-Archiv enthält kein `vendor/`. `composer install` installiert die in `composer.lock` festgelegten Versionen und erstellt `vendor/autoload.php`. Für eine normale Installation nicht `composer update` verwenden.

## Erstinstallation

Das Archiv entpacken und ein Terminal im Stammverzeichnis des Repositorys öffnen. Wer sich bereits in `apps/symfony` befindet, lässt den Befehl `cd` weg.

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

Die Operatoren `&&` stoppen die Befehlsfolge bei einem Fehler: diesen vor dem Fortfahren beheben. http://127.0.0.1:5412/ öffnen; Ctrl+C beendet den lokalen Server. Die Initialisierung erstellt `.env.local` nur, wenn die Datei fehlt. Die aktive Datenbank ist `var/populorum.sqlite`. Profiler-Cookies benötigen HTTPS, auch bei einem manuellen lokalen Test.

## Fehlerbehebung

Fehlt `vendor/autoload.php`, den Server stoppen und in `apps/symfony` den Befehl `composer install --no-interaction --prefer-dist` ausführen. Bei einem Fehler zuerst die gemeldete PHP-Version oder fehlenden Erweiterungen korrigieren, dann die Symfony-Befehle erneut ausführen. Nicht `--ignore-platform-reqs` verwenden. Beim Hosting muss der DocumentRoot auf `apps/symfony/public/` zeigen; PHP-FPM, Apache oder Nginx und HTTPS verwenden. Der integrierte PHP-Server dient nur lokalen Tests.

## Referenzdokumentation (Französisch)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
