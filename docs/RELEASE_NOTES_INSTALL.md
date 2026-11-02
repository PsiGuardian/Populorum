# Populorum v0.7.1 — édition propre

Distribution Symfony 8.1 / PHP ≥ 8.4, nettoyée des scripts de construction, de migration historique et des benchmarks. `tools/` ne contient plus que des fonctions d'exploitation durables ; tests isolés sous `tests/`.

La version 0.7.1 étend la sélection géographique : codes postaux en priorité, suggestions multi-communes non tronquées, accès à l'annuaire complet et vérifications HTTP. La base INSEE/La Poste est installée automatiquement avant la validation de l'application. `76600` indique Le Havre et `INSEE:76600` Saint-Léonard ; `66300` affiche ses 18 communes.

## Installer sur Arch Linux

```bash
sudo pacman -Syu --needed php php-sqlite composer git python curl tar
cd apps/symfony
composer install --no-interaction --prefer-dist --no-progress &&
test -f vendor/autoload.php &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:demo:prepare --env=demo &&
python3 tools/install_reference_indexes.py &&
python3 tools/verify_geography.py &&
php bin/console cache:clear --env=demo --no-debug &&
php bin/console doctrine:schema:validate --env=demo &&
python3 tests/http_smoke.py
APP_ENV=demo APP_DEBUG=0 php -S 127.0.0.1:5412 -t public
```

**Consulter le guide détaillé** `LIRE_AVANT_INSTALLATION.md` pour l'extraction du tarball, le contrôle SHA256, les extensions, l'arrêt au premier échec, les variantes de distribution et la sauvegarde des données existantes.

CI : installation à partir d'une archive propre, sans `vendor/` ni `.env.local`, puis requêtes HTTP réelles sous Arch Linux et Ubuntu. L'index DECP national reste optionnel et n'est pas inclus dans la release.

Les anciens commits GitHub conservent leur historique ; les fichiers supprimés ne figurent plus dans le contenu de cette release propre.
