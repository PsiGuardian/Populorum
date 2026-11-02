# Populorum — Installation et mise à jour

🇫🇷 **Français · fr_FR**

> Populorum est une simulation fictive, complémentaire aux institutions existantes. Aucun vote n’a d’effet juridique. Ne pas y saisir de données personnelles réelles ni de secrets de production.

## Prérequis

PHP 8.4 ou supérieur, Composer et SQLite sont nécessaires. Sur Arch Linux, installer les paquets avec pacman. Vérifier les extensions ci-dessous avec `php -m` ; activer dans `/etc/php/php.ini` uniquement les modules nécessaires qui ne sont pas déjà compilés dans PHP.

`openssl`, `pdo`, `pdo_sqlite`, `sqlite3`, `tokenizer`, `mbstring`, `ctype`, `iconv`, `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `curl`, `zlib`.

```bash
sudo pacman -S --needed php php-sqlite composer
php -v
php -m
```

L’archive GitHub ne contient pas `vendor/`. `composer install` installe les versions figées dans `composer.lock` et crée `vendor/autoload.php`. Ne pas utiliser `composer update` pour une installation normale.

## Première installation

Extraire l’archive puis ouvrir un terminal à la racine du dépôt. Si le terminal est déjà dans `apps/symfony`, ne pas répéter la commande `cd`.

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

Les `&&` arrêtent la séquence en cas d’erreur : la corriger avant de continuer. Ouvrir http://127.0.0.1:5412/ ; Ctrl+C arrête le serveur local. L’initialisation crée `.env.local` uniquement si le fichier n’existe pas. La base active est `var/populorum.sqlite`. Les cookies du profiler nécessitent HTTPS, même pour un essai manuel local.

## Mettre à jour

Arrêter le serveur et sauvegarder la base, les fichiers et les clés avant de remplacer les sources. Conserver `.env.local`, `.guardian-shield/` et `var/`. Ne jamais remplacer une base active par `data/populorum-demo.sqlite`. Depuis `apps/symfony`, exécuter :

```bash
composer install --no-interaction --prefer-dist &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo
```

Après réussite de toutes les étapes, relancer le serveur local :

```bash
php -S 127.0.0.1:5412 -t public
```

## Dépannage

Si `vendor/autoload.php` est introuvable, arrêter le serveur puis relancer `composer install --no-interaction --prefer-dist` depuis `apps/symfony`. En cas d’échec, corriger la version de PHP ou les extensions signalées avant de reprendre les commandes Symfony. Ne pas utiliser `--ignore-platform-reqs`. Pour une mise en ligne, le DocumentRoot doit être `apps/symfony/public/`, avec PHP-FPM, Apache ou Nginx et HTTPS ; le serveur PHP intégré est réservé aux essais locaux.

## Documentation de référence (français)

- [Symfony — fr_FR](../../../README.md)
- [Installation — fr_FR](../../../../../LIRE_AVANT_INSTALLATION.md)
- [Profiler Shield — fr_FR](../../../../../profiler-shield.md)
