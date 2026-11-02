# Populorum — installation propre, sans installateur spécifique à une distribution

**Sources officielles :** [Releases](https://github.com/PsiGuardian/Populorum/releases) | [application Symfony](apps/symfony/README.md) | [installation des données nationales](apps/symfony/docs/INSTALLATION_DONNEES_NATIONALES.md).

Populorum est un simulateur fictif (PHP 8.4+, Symfony 8.1, SQLite). Il se télécharge sans dépendances PHP, sans clés privées et sans index DECP national de ~1,7 Go. Les dépendances sont définies dans `apps/symfony/composer.lock`.

## 1. Paquets système

Sur **Arch Linux** :

```bash
sudo pacman -Syu --needed php php-sqlite composer git python curl tar
```

Sur d'autres distributions, adapter uniquement l'installation des paquets : PHP CLI ≥ 8.4, Composer 2, Python 3, SQLite via PDO, OpenSSL, XML, mbstring, curl et zlib. Les commandes Composer/Symfony ci-dessous sont identiques.

```bash
php -v
php -m
```

Si `pdo_sqlite` ou `sqlite3` n'apparaissent pas, vérifier les modules disponibles puis les activer une seule fois dans `/etc/php/php.ini` ou `/etc/php/conf.d/` selon la distribution. **Ne pas ajouter une extension déjà chargée**.

## 2. Télécharger une version stable

Exemple avec la version propre (voir la page Releases pour le dernier tag disponible) :

```bash
mkdir -p "$HOME/Téléchargements/populorum-clean"
cd "$HOME/Téléchargements/populorum-clean"

curl -fLO https://github.com/PsiGuardian/Populorum/releases/download/v0.9.0-clean/Populorum-v0.9.0-clean.tar.gz
curl -fLO https://github.com/PsiGuardian/Populorum/releases/download/v0.9.0-clean/SHA256SUMS.txt
sha256sum -c SHA256SUMS.txt
tar -xzf Populorum-v0.9.0-clean.tar.gz
cd Populorum-v0.9.0-clean/apps/symfony
```

Le tag de cette section doit être vérifié sur la page [Releases](https://github.com/PsiGuardian/Populorum/releases) avant toute installation. Pour une archive de branche `main`, extraire son ZIP et se placer simplement dans `Populorum-main/apps/symfony`.

## 3. Installer directement dans le terminal

Contrôler les extensions :

```bash
php -r '$r=["openssl","pdo","pdo_sqlite","sqlite3","tokenizer","mbstring","iconv","dom","simplexml","curl","zlib"];foreach($r as $x){if(!extension_loaded($x)){fwrite(STDERR,"Extension PHP absente : $x\n");exit(1);}}'
```

Installer Composer, générer **une seule fois** les clés locales, appliquer les migrations, restaurer l'index INSEE avec Symfony, préparer la démonstration et effectuer des vérifications HTTP :

```bash
composer install --no-interaction --prefer-dist --no-progress &&
test -f vendor/autoload.php &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Cannot create .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:geography:install --check --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo --no-debug &&
php bin/console doctrine:schema:validate --env=demo &&
python3 tests/http_smoke.py
```

Les opérateurs `&&` arrêtent la chaîne au premier échec. `tests/http_smoke.py` nécessite Python 3 uniquement pour les tests de non-régression ; l'installation des données INSEE n'en dépend pas. La ligne PHP crée `.env.local` avec des permissions 0600 sans écraser des clés déjà existantes. **Ne jamais exécuter `composer update` pour installer une release** : `composer install` utilise le fichier de verrouillage versionné.

Seulement si toutes les étapes sont validées :

```bash
APP_ENV=demo APP_DEBUG=0 php -S 127.0.0.1:5412 -t public
```

Ouvrir **http://127.0.0.1:5412/**. Arrêter avec Ctrl+C. Le serveur intégré PHP convient aux tests locaux, pas à un service public.

## 4. Référentiel national et espaces communaux

L’installation synchronise désormais les **34 875 communes officielles INSEE 2026** dans Doctrine. Choisir une commune par nom ou code postal ouvre un espace de simulation **indépendant et vide** (pas de population, de vote ou de budget fictif attribué automatiquement). Les codes à 2–4 chiffres recherchent exclusivement des préfixes postaux ; 5 chiffres désignent un code postal exact. Pour un code administratif : `INSEE:76600`.

## 5. Installer les référentiels nationaux INSEE et DECP

L'archive `data/geography/communes-2026.sqlite.gz` est incluse dans les sources. **`app:demo:prepare` restaure automatiquement le référentiel manquant** avant de synchroniser les communes dans Doctrine. Pour installer ou vérifier explicitement avec PHP, hors réseau :

```bash
php bin/console app:geography:install --env=demo
php bin/console app:geography:install --check --env=demo
```

Pour installer le référentiel depuis GitHub Releases et, après sa mise en ligne, la base nationale DECP **en trois parties vérifiées**, suivre le [guide détaillé des données nationales](apps/symfony/docs/INSTALLATION_DONNEES_NATIONALES.md). Cette opération est indépendante de `var/populorum.sqlite` et ne doit pas écraser les données existantes.

## 6. Mettre à jour sans perdre les données

Avant toute mise à jour, arrêter PHP et sauvegarder **`.env.local`, `var/`, `.guardian-shield/`**, ainsi que les bases nationales séparées. Ne jamais les écraser par les fixtures de la release. Après extraction des nouveaux fichiers sources, depuis `apps/symfony`, relancer les mêmes étapes de la section 3. La génération de clés est idempotente : elle conserve l'existant.

## 7. Diagnostic et limites

- `vendor/autoload.php` absent : exécuter `composer install` depuis `apps/symfony` et vérifier sa réussite ; le serveur ne peut pas fonctionner sans les dépendances.
- HTTP 500 : conserver l'erreur exacte de PHP, consulter `var/log/demo.log` si présent, puis `python3 tests/http_smoke.py` pour reproduire.
- Extensions absentes : installer/activer les extensions **pour le PHP CLI** utilisé par Composer, sans `--ignore-platform-reqs`.
- HTTPS et PHP-FPM : pour un déploiement réel, faire pointer DocumentRoot vers `apps/symfony/public/` et protéger les clés privées.
- Référentiel INSEE + index DECP : voir [INSTALLATION_DONNEES_NATIONALES.md](apps/symfony/docs/INSTALLATION_DONNEES_NATIONALES.md).

Les outils de construction et correctifs historiques ont été supprimés de la distribution. Seuls les outils opérationnels pérennes et les tests isolés sont conservés.

## 8. Atlas national des communes et convergence

La page `/convergence` explore les 34 875 communes grâce à des filtres région/département et des pages de 36 fiches. Les valeurs statistiques réelles 2024–2025 sont consultées exclusivement lorsque l’index officiel SSMSI est installé. Le mode d'import, ainsi que la reconstruction de DECP depuis trois fragments GitHub, accompagnés d'un manifeste et d'empreintes SHA-256, sont décrits dans le [guide des données nationales](apps/symfony/docs/INSTALLATION_DONNEES_NATIONALES.md). Une valeur non diffusée reste inconnue, et ne devient jamais zéro.
