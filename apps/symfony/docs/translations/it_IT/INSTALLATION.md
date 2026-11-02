# Populorum — Installazione e aggiornamento

🇮🇹 **Italiano · it_IT**

> Populorum è una simulazione fittizia che affianca le istituzioni esistenti. I voti non hanno effetti giuridici. Non inserire dati personali reali o segreti di produzione.

## Requisiti

Sono necessari PHP 8.4 o successivo, Composer e SQLite. Su Arch Linux, installare i pacchetti con pacman. Verificare le estensioni elencate sotto con `php -m`; attivare in `/etc/php/php.ini` solo i moduli necessari non già compilati in PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

L’archivio GitHub non include `vendor/`. `composer install` installa le versioni fissate in `composer.lock` e crea `vendor/autoload.php`. Non usare `composer update` per un’installazione normale.

## Prima installazione

Estrarre l’archivio e aprire un terminale nella radice del repository. Se ci si trova già in `apps/symfony`, non ripetere il comando `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Gli operatori `&&` interrompono la sequenza se un passaggio fallisce: correggere l’errore prima di continuare. Aprire http://127.0.0.1:5412/; Ctrl+C arresta il server locale. L’inizializzazione crea `.env.local` solo se non esiste. Il database attivo è `var/populorum.sqlite`. I cookie del profiler richiedono HTTPS, anche per una prova manuale locale.

## Aggiornamento

Arrestare il server e salvare database, file e chiavi prima di sostituire i sorgenti. Conservare `.env.local`, `.guardian-shield/` e `var/`. Non sostituire mai un database attivo con `data/populorum-demo.sqlite`. Da `apps/symfony`, eseguire:

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Dopo il completamento di tutti i passaggi, riavviare il server locale:

```bash
php -S 127.0.0.1:5412 -t public
```

## Risoluzione dei problemi

Se manca `vendor/autoload.php`, arrestare il server ed eseguire `composer install --no-interaction --prefer-dist` da `apps/symfony`. In caso di errore, correggere la versione di PHP o le estensioni segnalate prima di riprendere i comandi Symfony. Non usare `--ignore-platform-reqs`. Per l’hosting, il DocumentRoot deve essere `apps/symfony/public/`, con PHP-FPM, Apache o Nginx e HTTPS; il server PHP integrato serve solo per prove locali.

## Documentazione di riferimento (francese)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
