# Populorum — installation complète (v0.12.0-clean)

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
| Catalogue des propositions de loi du Sénat | **Facultatif**, CSV officiel importé après installation | `/chemins` et `/consultations` restent sans dossier parlementaire avant import |
| Rapports et exposés des motifs du Sénat | **Facultatif**, index documentaire construit par lots | Les fiches ne comportent pas encore de bibliothèque documentaire locale |

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

VERSION="v0.12.0-clean"
SOURCE="https://github.com/PsiGuardian/Populorum/releases/download/$VERSION"
curl -fL --retry 3 -O "$SOURCE/Populorum-$VERSION.tar.gz"
curl -fL --retry 3 -O "$SOURCE/SHA256SUMS.txt"
sha256sum -c SHA256SUMS.txt
tar -xzf "Populorum-$VERSION.tar.gz"
cd "Populorum-$VERSION/apps/symfony"
```

L'archive ne contient ni `vendor/`, ni bases privées, ni clés de production. En cas d'échec du SHA-256, ne pas installer. Cette version devient téléchargeable après publication réussie de la release `v0.12.0-clean`.

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

## 5. Facultatif — reconstruire l'index national des marchés publics DECP v2

Le référentiel DECP v2 est **reconstruit à partir des fichiers JSON officiels**
publiés par les [Ministères économiques et financiers sur data.gouv.fr](https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides).
Il ne réutilise pas l'archive historique `public-procurement.sqlite.tar.gz`
de la release `data-national-2026`, qui contient un **schéma v1 incompatible**.

Les données essentielles décrivent des marchés et des concessions notifiés :
**leur montant déclaré ne prouve ni paiement ni coût final**. Les motifs et
résultats ne sont affichés comme documentés que si une pièce précise et son
URL vérifiable leur sont rattachées. Les fichiers sources, leurs empreintes
et les dates de publication sont conservés.

Depuis `apps/symfony`, en conservant les autres bases locales :

```bash
# Contrôler l'espace disponible : sources, index v2, ancienne base et sauvegarde.
df -h .

# Télécharger ou rafraîchir les ressources officielles.
# Les fichiers déjà vérifiés sont conservés si leur révision est inchangée.
python3 tools/decp_pipeline.py download

# Construire l'index v2 dans une base de travail distincte.
# L'index public existant reste inchangé.
python3 tools/decp_pipeline.py import

# Vérifier le schéma, les liens et les déclarations sources.
python3 tools/decp_pipeline.py verify
```

Pour publier **uniquement après réussite de la vérification**, arrêter
entièrement le serveur Symfony et tout lecteur SQLite de l'index DECP, puis :

```bash
python3 tools/decp_pipeline.py publish --site-stopped
python3 tools/decp_pipeline.py status
```

La commande `publish` conserve une sauvegarde de l'ancienne base dans
`data/research/decp-backups/` puis remplace l'index de façon contrôlée.
Ne jamais passer `--site-stopped` si le serveur fonctionne encore.
**Aucune suppression manuelle de la base actuelle n'est nécessaire.**
Aucun `cache:clear` ni script de migration temporaire n'est requis.

Le téléchargement peut représenter plusieurs gigaoctets. Il conserve les
URL **exactes** renvoyées par data.gouv.fr dans `resource-catalog.json`
et vérifie la révision, la taille et le SHA-256 dans
`resource-catalog-meta.json`. Si l'URL précise est inconnue pour un
ancien fichier importé, Populorum l'indique et renvoie au catalogue au
lieu de prétendre disposer de l'URL directe.

Le guide technique détaillé se trouve dans
[PROCUREMENT_EVIDENCE_V2.md](apps/symfony/docs/PROCUREMENT_EVIDENCE_V2.md).
Il décrit les preuves, leurs statuts et la recherche BOAMP, qui ne transforme
jamais automatiquement une correspondance textuelle en justification.

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
  test "$(sqlite3 -readonly "$TEMP" "SELECT value FROM metadata WHERE key='schema';")" = "populorum-ssmsi-national-1" &&
  test "$(sqlite3 -readonly "$TEMP" "SELECT 1 FROM facts LIMIT 1;")" = "1" &&
  ln "$TEMP" "$SSMSI" && rm "$TEMP"
fi
php bin/console cache:clear --env=demo --no-debug
```

Le téléchargement SSMSI suit lui aussi le mode rapide (SHA-256 et contrôle de quelques lignes), sans relire tout le fichier. L'absence de statistique publiée n'est **jamais** transformée en valeur zéro.

## 7. Facultatif — importer les propositions de loi et les ressources du Sénat

La release contient **le code, mais pas de catalogue parlementaire rempli**.
L'administrateur construit une base SQLite à partir des données officielles,
sans réutiliser un ancien cache de rapports ni importer de propositions fictives.
Aucun bulletin ou arbre de délibération personnel n'est inclus dans l'archive.

Depuis `apps/symfony` :

```bash
mkdir -p var

curl -fLsS --retry 3 \
  'https://www.data.gouv.fr/api/1/datasets/r/7698274a-65f6-4687-8b2b-6961199b6922' \
  -o /tmp/populorum-propositions-senat.csv

python3 tools/import_policy_catalog.py \
  --senat-csv /tmp/populorum-propositions-senat.csv \
  --limit 10000 --days 90
```

**Vérifier que le téléchargement est bien le CSV officiel attendu** avant
l'importation. Le nombre de dossiers dépend des textes admissibles ; aucun
dossier fictif ne sera ajouté pour remplir un quota.

Pour enrichir les rapports, exposés des motifs, amendements, textes et débats
associés à **tous les dossiers importés** (jusqu'à 2 000 par lot) :

```bash
python3 tools/enrich_senate_dossiers.py \
  --limit 2000 --offset 0 --pause 0.6 --timeout 20
```

Sur une base neuve, l'indexeur sélectionne uniquement les documents
réellement rattachés au dossier. Les liens vers les répertoires généraux
« Derniers rapports » et « Tous les rapports » sont exclus.
Les téléchargements parlementaires sont déclenchés explicitement depuis
le terminal, jamais à l'ouverture de pages Web.

**En cas de mise à jour d'une installation contenant déjà des votes ou
délibérations :** ne pas effacer `var/policy-catalog.sqlite`. La release
propre n'en contient aucune. Sauvegarder l'état existant et procéder à une
migration séparée si ces données doivent être conservées.

Voir [la documentation du catalogue législatif](apps/symfony/docs/POLICY_CATALOG.md).

## 8. Démarrer et vérifier le site

```bash
php bin/console doctrine:schema:validate --env=demo
php bin/console debug:router --env=demo
APP_ENV=demo APP_DEBUG=0 php -S 127.0.0.1:5412 -t public
```

Ouvrir dans le navigateur : **http://127.0.0.1:5412/**, puis `/communes`, `/convergence`, `/marches-publics` et `/compte-test`. Le serveur PHP intégré sert uniquement aux essais locaux. Sur un hébergement public, exposer uniquement `apps/symfony/public/`, utiliser HTTPS, un proxy configuré et des permissions privées.

Le **compte de test facultatif** propose nom, prénom, âge et commune ; noms fictifs autorisés. Il est sans privilège, reconnu par une empreinte HMAC de l'IP (sans cookie propre au compte) et ne garantit pas une identité. Un même réseau public peut partager le profil. Les informations de confidentialité figurent sur `/confidentialite`.

## 9. Mettre à jour sans perte de données

Avant une mise à jour, arrêter le serveur et sauvegarder `.env.local`, `var/`, `.guardian-shield/` et les répertoires de bases `data/geography/` et `data/research/`. Après extraction d'une nouvelle archive, rétablir ces données privées puis relancer **Composer, les migrations et les contrôles** de la section 3. Ne jamais écraser la base de simulation ni les référentiels avec des fichiers de démonstration.

Pour un HTTP 500, consulter les erreurs serveur et `var/log/demo.log` ; pour une extension manquante, vérifier `php -m`. Si un index DECP ou SSMSI est absent, la section concernée n'aura pas le contenu national correspondant, mais le reste du simulateur doit rester disponible.

**Documentation de performance :** [mesure des temps de réponse](https://github.com/PsiGuardian/Populorum/blob/main/docs/PERFORMANCE.md). Pour toute installation, les commandes nécessaires figurent déjà sur **cette page**.
