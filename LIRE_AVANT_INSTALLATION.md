# Populorum — installation propre, sans installateur spécifique à une distribution

**Sources officielles :** [Releases](https://github.com/PsiGuardian/Populorum/releases) | [application Symfony](apps/symfony/README.md) | [installation des données nationales](apps/symfony/docs/INSTALLATION_DONNEES_NATIONALES.md).

Populorum est un simulateur fictif (PHP 8.4+, Symfony 8.1, SQLite). Il se télécharge sans dépendances PHP, sans clés privées et sans l'index DECP national volumineux (archive compressée séparée). Les dépendances sont définies dans `apps/symfony/composer.lock`.

## 1. Paquets système

Sur **Arch Linux** :

```bash
sudo pacman -Syu --needed php php-sqlite composer git python curl tar sqlite coreutils
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

curl -fLO https://github.com/PsiGuardian/Populorum/releases/download/v0.10.0-clean/Populorum-v0.10.0-clean.tar.gz
curl -fLO https://github.com/PsiGuardian/Populorum/releases/download/v0.10.0-clean/SHA256SUMS.txt
sha256sum -c SHA256SUMS.txt
tar -xzf Populorum-v0.10.0-clean.tar.gz
cd Populorum-v0.10.0-clean/apps/symfony
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

## 5. Télécharger INSEE et les marchés publics depuis GitHub Releases

La release [data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026) contient les deux bases : communes-2026.sqlite (6,84 Mo, 34 875 communes et codes postaux La Poste) et public-procurement.sqlite.tar.gz (486 882 207 octets, environ 464 Mio compressés). Prévoir plusieurs Go libres pour l'extraction DECP. Ces bases ne remplacent jamais la base de simulation.

### INSEE : téléchargement et vérification

Le référentiel INSEE peut déjà être restauré sans réseau depuis l'archive compressée livrée avec les sources via php bin/console app:geography:install --env=demo. Pour télécharger explicitement la version GitHub, exécuter depuis apps/symfony :

~~~bash
DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026"
mkdir -p data/geography var/reference-downloads
INSEE="data/geography/communes-2026.sqlite"
TEMP="var/reference-downloads/communes-2026.sqlite"

if test -e "$INSEE"; then
  echo "INSEE déjà présent : conservation sans écrasement"
else
  curl -fL --retry 3 -C - -o "$TEMP" "$DATA_URL/communes-2026.sqlite" || exit 1
  printf '%s  %s\n' \
    '40449a3c1bd346d83f4330b1a6dc6486ecdd6942ff34a01e19c38ce12d247b82' \
    "$TEMP" | sha256sum -c - || exit 1
  chmod 644 "$TEMP"
  ln "$TEMP" "$INSEE" || exit 1
fi
php bin/console app:geography:install --check --env=demo
~~~

### DECP : téléchargement et extraction de l'archive unique

Il n'y a plus de fragments et aucun script temporaire n'est nécessaire. Toujours depuis apps/symfony :

~~~bash
DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026"
mkdir -p data/research var/reference-downloads
ARCHIVE="var/reference-downloads/public-procurement.sqlite.tar.gz"
DECP="data/research/public-procurement.sqlite"
TEMP="$DECP.restoring"

if test -e "$DECP" || test -e "$TEMP"; then
  echo "DECP déjà présent ou restauration en cours : aucun écrasement"
else
  curl -fL --retry 3 -C - -o "$ARCHIVE" \
    "$DATA_URL/public-procurement.sqlite.tar.gz" || exit 1
  printf '%s  %s\n' \
    '37d8fd44cbd607d8459ec8f5f6e5a97250c576cca0727fd525106f9e87932f41' \
    "$ARCHIVE" | sha256sum -c - || exit 1

  MEMBER="$(tar -tzf "$ARCHIVE" | grep -E '(^|/)public-procurement\.sqlite$')"
  test "$(printf '%s\n' "$MEMBER" | grep -c .)" -eq 1 || exit 1
  tar -xOzf "$ARCHIVE" "$MEMBER" > "$TEMP" || exit 1
  test "$(sqlite3 -readonly "$TEMP" 'PRAGMA quick_check;')" = "ok" || exit 1
  test "$(sqlite3 -readonly "$TEMP" \
    "SELECT value FROM metadata WHERE key='schema';")" = "populorum-decp-1" || exit 1
  ln "$TEMP" "$DECP" && rm "$TEMP"
fi

python3 tools/decp_pipeline.py status
php bin/console cache:clear --env=demo --no-debug
~~~

Le SHA-256 contrôle l'archive publiée, puis SQLite contrôle la base décompressée. En cas d'erreur de contrôle, inspecter le téléchargement au lieu de remplacer une base existante. L'index SSMSI 2024–2025 reste facultatif et possède sa propre procédure dans le [guide national](apps/symfony/docs/INSTALLATION_DONNEES_NATIONALES.md).

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

La page `/convergence` explore les 34 875 communes grâce à des filtres région/département et des pages de 36 fiches. Les valeurs statistiques réelles 2024–2025 sont consultées exclusivement lorsque l’index officiel SSMSI est installé. Le mode d'import, ainsi que l'installation de l'unique archive DECP publiée et vérifiée par SHA-256, sont décrits dans le [guide des données nationales](apps/symfony/docs/INSTALLATION_DONNEES_NATIONALES.md). Une valeur non diffusée reste inconnue, et ne devient jamais zéro.

## 9. URL de recherche et données DECP

Les filtres publics des marchés, des communes, de la convergence et du budget sont représentés par des segments de chemin. Exemple : `/marches-publics/q/web/kind/market/year/2025/sort/amount_desc`. Les anciennes URL contenant `?q=...` sont redirigées vers un chemin canonique ; les critères vides sont ignorés. Les formulaires sans JavaScript restent opérationnels.

Les marchés sont triables par montant contractuel ou date déclarée, croissants ou décroissants. Les dates aberrantes restent visibles et consultables avec leur valeur source ; un motif de contrôle automatique est affiché, sans présenter cette classification comme une justification donnée par l'acheteur.

## 10. Compte de test facultatif

La page /compte-test propose quatre champs : prénom, nom, âge, commune INSEE. Des noms fictifs sont acceptés. Le profil est limité à la démonstration, sans privilège ni identification réelle, et n'utilise aucun cookie propre au compte : une empreinte HMAC de l'adresse IP est conservée 30 jours. Les connexions partageant une IP peuvent accéder au même profil ; aucune information sensible ne doit y figurer. La page /confidentialite détaille ce traitement. En production derrière un proxy, configurer les trusted proxies de façon fiable et ne jamais faire confiance directement à X-Forwarded-For.
