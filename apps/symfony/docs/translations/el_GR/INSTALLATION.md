# Populorum — Εγκατάσταση και ενημέρωση

🇬🇷 **Ελληνικά · el_GR**

> Το Populorum είναι μια φανταστική προσομοίωση που συμπληρώνει τους υφιστάμενους θεσμούς. Οι ψήφοι δεν έχουν νομική ισχύ. Μην εισάγετε πραγματικά προσωπικά δεδομένα ή μυστικά του περιβάλλοντος παραγωγής.

## Προϋποθέσεις

Απαιτούνται PHP 8.4 ή νεότερη έκδοση, Composer και SQLite. Στο Arch Linux εγκαταστήστε τα πακέτα με το pacman. Ελέγξτε τις παρακάτω επεκτάσεις με `php -m`· ενεργοποιήστε στο `/etc/php/php.ini` μόνο τις απαραίτητες μονάδες που δεν είναι ήδη ενσωματωμένες στην PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

Το αρχείο του GitHub δεν περιλαμβάνει το `vendor/`. Η εντολή `composer install` εγκαθιστά τις εκδόσεις που έχουν καθοριστεί στο `composer.lock` και δημιουργεί το `vendor/autoload.php`. Μη χρησιμοποιείτε `composer update` για μια συνηθισμένη εγκατάσταση.

## Πρώτη εγκατάσταση

Αποσυμπιέστε το αρχείο και ανοίξτε ένα τερματικό στη ρίζα του αποθετηρίου. Αν βρίσκεστε ήδη στο `apps/symfony`, παραλείψτε την εντολή `cd`.

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo &&
php -S 127.0.0.1:5412 -t public
```

Οι τελεστές `&&` διακόπτουν τη σειρά εντολών σε περίπτωση σφάλματος: διορθώστε το πριν συνεχίσετε. Ανοίξτε το http://127.0.0.1:5412/· το Ctrl+C σταματά τον τοπικό διακομιστή. Η αρχικοποίηση δημιουργεί το `.env.local` μόνο αν δεν υπάρχει. Η ενεργή βάση είναι το `var/populorum.sqlite`. Τα cookies του profiler απαιτούν HTTPS, ακόμη και σε χειροκίνητη τοπική δοκιμή.

## Ενημέρωση

Σταματήστε τον διακομιστή και δημιουργήστε αντίγραφα ασφαλείας της βάσης, των αρχείων και των κλειδιών πριν αντικαταστήσετε τον πηγαίο κώδικα. Διατηρήστε τα `.env.local`, `.guardian-shield/` και `var/`. Μην αντικαθιστάτε ποτέ μια ενεργή βάση με το `data/populorum-demo.sqlite`. Από το `apps/symfony`, εκτελέστε:

```bash
composer install --no-interaction --prefer-dist &&
php tools/init-demo.php &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Αφού ολοκληρωθούν επιτυχώς όλα τα βήματα, επανεκκινήστε τον τοπικό διακομιστή:

```bash
php -S 127.0.0.1:5412 -t public
```

## Αντιμετώπιση προβλημάτων

Αν λείπει το `vendor/autoload.php`, σταματήστε τον διακομιστή και εκτελέστε `composer install --no-interaction --prefer-dist` από το `apps/symfony`. Αν αποτύχει, διορθώστε την έκδοση PHP ή τις επεκτάσεις που αναφέρονται πριν επαναλάβετε τις εντολές Symfony. Μη χρησιμοποιείτε `--ignore-platform-reqs`. Για φιλοξενία, το DocumentRoot πρέπει να είναι `apps/symfony/public/`, με PHP-FPM, Apache ή Nginx και HTTPS· ο ενσωματωμένος διακομιστής PHP προορίζεται μόνο για τοπικές δοκιμές.

## Τεκμηρίωση αναφοράς (γαλλικά)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
