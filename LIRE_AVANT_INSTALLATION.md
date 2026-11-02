# Populorum — installation complète (v0.10.0-clean)

**Ce guide est autonome :** téléchargement de l'application, installation Symfony, téléchargement facultatif des bases nationales, contrôles et démarrage. Les commandes se lancent directement dans le terminal ; aucun installateur de distribution ni script ponctuel n'est nécessaire.

**Populorum est une simulation fictive.** Les référentiels nationaux servent à présenter des informations publiques sourcées, jamais à inventer des habitants, des votes ou des dépenses.

## Quelles données sont installées ?

| Composant | Statut | Sans ce composant |
| --- | --- | --- |
| Application Symfony et SQLite de simulation | **Obligatoire** | Le site ne démarre pas |
| INSEE 2026 / La Poste : 34 875 communes, 35 313 correspondances postales | **Inclus dans les sources** ; téléchargement indépendant facultatif | Sans le référentiel installé, impossible de sélectionner correctement une commune ; l'installation normale le restaure automatiquement |
| Marchés publics nationaux (DECP) | **Facultatif**, téléchargement séparé | Le site fonctionne, mais `/marches-publics` et les rapprochements DECP restent sans données nationales |
| Statistiques SSMSI 2024–2025 | **Facultatif**, téléchargement séparé | L'atlas `/convergence` montre les communes, mais pas les observations nationales correspondantes |
| Budgets communaux OFGL | **Jeu distinct**, non livré avec les données DECP | Les sections budgétaires dépendant de cet import n'affichent pas de comptes réels |

L'installation de l'application reste possible sans télécharger la volumineuse archive DECP. Les pages de données réelles non alimentées ne doivent **jamais** afficher des résultats inventés.

## 1. Prérequis

Sur **Arch Linux** :

```bash
sudo pacman -Syu --needed php php-sqlite composer git python curl tar sqlite coreutils
php -v
php -r '$r=["openssl","pdo","pdo_sqlite","sqlite3","tokenizer","mbstring","iconv","dom","simplexml","curl","zlib"];foreach($r as $x){if(!extension_loaded($x)){fwrite(STDERR,"Extension PHP manquante : $x\n");exit(1);}}'
```

Sur une autre distribution, installer les équivalents : **PHP 8.4+**, Composer 2, PDO SQLite, SQLite CLI, OpenSSL, mbstring, XML, cURL, zlib, Python 3 et tar. Les commandes suivantes sont identiques. Ne pas activer deux fois une extension PHP déjà chargée.

## 2. Télécharger l'application et vérifier l'archive

```bash
mkdir -p "$HOME/Téléchargements/populorum-clean"
cd "$HOME/Téléchargements/populorum-clean"

VERSION="v0.10.0-clean"
SOURCE="https://github.com/PsiGuardian/Populorum/releases/download/$VERSION"
curl -fL --retry 3 -O "$SOURCE/Populorum-$VERSION.tar.gz"
curl -fL --retry 3 -O "$SOURCE/SHA256SUMS.txt"
sha256sum -c SHA256SUMS.txt
tar -xzf "Populorum-$VERSION.tar.gz"
cd "Populorum-$VERSION/apps/symfony"
```

L'archive ne contient ni `vendor/`, ni bases privées, ni clés de production. En cas d'échec du SHA-256, ne pas installer. Cette version devient téléchargeable après publication réussie de la release `v0.10.0-clean`.

## 3. Installer Symfony (données de démonstration incluses)

Depuis **`apps/symfony`** :

```bash
composer install --no-interaction --prefer-dist --no-progress &&
test -f vendor/autoload.php &&
php -r '$p=".env.local";if(!file_exists($p)){$h=@fopen($p,"x");if(!$h){fwrite(STDERR,"Impossible de créer .env.local\n");exit(1);}chmod($p,0600);fwrite($h,"APP_SECRET=".bin2hex(random_bytes(32))."\nGUARDIAN_ENCRYPTION_KEY=".bin2hex(random_bytes(32))."\n");fclose($h);}' &&
php bin/console doctrine:migrations:migrate --no-interaction --env=demo &&
php bin/console app:geography:install --env=demo &&
php bin/console app:geography:install --check --env=demo &&
php bin/console app:demo:prepare --env=demo &&
php bin/console cache:clear --env=demo --no-debug &&
php bin/console doctrine:schema:validate --env=demo &&
python3 tests/http_smoke.py
```

Ces commandes ne remplacent pas `.env.local` existant. **Utiliser `composer install`, pas `composer update`.** Le référentiel INSEE est automatiquement restauré à partir de `data/geography/communes-2026.sqlite.gz`, inclus dans la distribution : son téléchargement sur Internet n'est pas nécessaire pour obtenir les communes.

Les deux sections suivantes sont **facultatives** : elles enrichissent les pages publiques sans modifier les scénarios ou les comptes de démonstration.

## 4. Facultatif — télécharger la base INSEE 2026 directement

À utiliser si vous souhaitez récupérer explicitement la base publiée sur GitHub au lieu de la copie compressée déjà fournie. Depuis `apps/symfony` :

```bash
DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026"
INSEE="data/geography/communes-2026.sqlite"
TEMP="data/geography/communes-2026.sqlite.download"
mkdir -p data/geography

if test -e "$INSEE"; then
  echo "INSEE déjà installé : base existante conservée."
else
  curl -fL --retry 3 -C - -o "$TEMP" "$DATA_URL/communes-2026.sqlite" &&
  printf '%s  %s\n' '40449a3c1bd346d83f4330b1a6dc6486ecdd6942ff34a01e19c38ce12d247b82' "$TEMP" | sha256sum -c - &&
  ln "$TEMP" "$INSEE" && rm "$TEMP"
fi
php bin/console app:geography:install --check --env=demo
```

**Important :** si vous avez déjà exécuté l'étape 3, la base INSEE existe normalement et sera conservée. La commande ne l'écrase jamais. La base officielle est aussi téléchargeable individuellement à cette adresse : [communes-2026.sqlite](https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026/communes-2026.sqlite).

## 5. Facultatif — télécharger la base nationale des marchés publics

Il s'agit de l'archive **unique** [public-procurement.sqlite.tar.gz](https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026/public-procurement.sqlite.tar.gz) (486 882 207 octets, environ 464 Mio compressés). **Prévoir plusieurs gigaoctets libres** pour la décompression. Aucune division en plusieurs fragments, aucun installateur externe.

Toujours depuis **`apps/symfony`**, de préférence serveur arrêté :

```bash
DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026"
ARCHIVE="var/reference-downloads/public-procurement.sqlite.tar.gz"
DECP="data/research/public-procurement.sqlite"
TEMP="data/research/public-procurement.sqlite.restoring"
mkdir -p var/reference-downloads data/research

if test -e "$DECP"; then
  echo "DECP déjà installée : base existante conservée."
elif test -e "$TEMP"; then
  echo "Restauration interrompue détectée : examiner $TEMP avant de recommencer."
else
  curl -fL --retry 3 -C - -o "$ARCHIVE" "$DATA_URL/public-procurement.sqlite.tar.gz" &&
  printf '%s  %s\n' '37d8fd44cbd607d8459ec8f5f6e5a97250c576cca0727fd525106f9e87932f41' "$ARCHIVE" | sha256sum -c - &&
  MEMBER="$(tar -tzf "$ARCHIVE" | grep -E '(^|/)public-procurement\.sqlite$')" &&
  test "$(printf '%s\n' "$MEMBER" | grep -c .)" -eq 1 &&
  tar -xOzf "$ARCHIVE" "$MEMBER" > "$TEMP" &&
  test "$(sqlite3 -readonly "$TEMP" 'PRAGMA quick_check;')" = "ok" &&
  test "$(sqlite3 -readonly "$TEMP" "SELECT value FROM metadata WHERE key='schema';")" = "populorum-decp-1" &&
  ln "$TEMP" "$DECP" && rm "$TEMP"
fi

python3 tools/decp_pipeline.py status
php bin/console cache:clear --env=demo --no-debug
```

Si une vérification échoue, **ne pas utiliser le fichier `.restoring`** ni remplacer une base déjà existante. L'archive DECP appartient à une [release de données distincte](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026) pour ne pas alourdir l'archive du code. Les montants affichés sont des **montants contractuels déclarés**, pas des paiements réalisés ; les dates suspectes restent visibles et signalées.

## 6. Facultatif — statistiques communales SSMSI pour la convergence

Si vous souhaitez également renseigner les indicateurs officiels 2024–2025 sur `/convergence`, l'index publié est directement disponible, sans reconstruire le CSV :

```bash
DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026"
SSMSI="data/research/ssmsi-national-2025.sqlite"
TEMP="data/research/ssmsi-national-2025.sqlite.download"
mkdir -p data/research

if test -e "$SSMSI"; then
  echo "SSMSI déjà installée : conservation de la base."
else
  curl -fL --retry 3 -C - -o "$TEMP" "$DATA_URL/ssmsi-national-2025.sqlite" &&
  printf '%s  %s\n' 'ada72643ad0f54c718ee6a825ae587bf2ba1723c8370ae5665529f8211d52a6c' "$TEMP" | sha256sum -c - &&
  test "$(sqlite3 -readonly "$TEMP" 'PRAGMA quick_check;')" = "ok" &&
  test "$(sqlite3 -readonly "$TEMP" "SELECT value FROM metadata WHERE key='schema';")" = "populorum-ssmsi-national-1" &&
  ln "$TEMP" "$SSMSI" && rm "$TEMP"
fi
php bin/console cache:clear --env=demo --no-debug
```

L'absence de statistique publiée n'est **jamais** transformée en valeur zéro.

## 7. Démarrer et vérifier le site

```bash
php bin/console doctrine:schema:validate --env=demo
php bin/console debug:router --env=demo
APP_ENV=demo APP_DEBUG=0 php -S 127.0.0.1:5412 -t public
```

Ouvrir dans le navigateur : **http://127.0.0.1:5412/**, puis `/communes`, `/convergence`, `/marches-publics` et `/compte-test`. Le serveur PHP intégré sert uniquement aux essais locaux. Sur un hébergement public, exposer uniquement `apps/symfony/public/`, utiliser HTTPS, un proxy configuré et des permissions privées.

Le **compte de test facultatif** propose nom, prénom, âge et commune ; noms fictifs autorisés. Il est sans privilège, reconnu par une empreinte HMAC de l'IP (sans cookie propre au compte) et ne garantit pas une identité. Un même réseau public peut partager le profil. Les informations de confidentialité figurent sur `/confidentialite`.

## 8. Mettre à jour sans perte de données

Avant une mise à jour, arrêter le serveur et sauvegarder `.env.local`, `var/`, `.guardian-shield/` et les répertoires de bases `data/geography/` et `data/research/`. Après extraction d'une nouvelle archive, rétablir ces données privées puis relancer **Composer, les migrations et les contrôles** de la section 3. Ne jamais écraser la base de simulation ni les référentiels avec des fichiers de démonstration.

Pour un HTTP 500, consulter les erreurs serveur et `var/log/demo.log` ; pour une extension manquante, vérifier `php -m`. Si un index DECP ou SSMSI est absent, la section concernée n'aura pas le contenu national correspondant, mais le reste du simulateur doit rester disponible.

**Documentation de performance :** [mesure des temps de réponse](https://github.com/PsiGuardian/Populorum/blob/main/docs/PERFORMANCE.md). Pour toute installation, les commandes nécessaires figurent déjà sur **cette page**.
