# Installation des données nationales — Populorum

Ce guide complète [LIRE_AVANT_INSTALLATION.md](../../../LIRE_AVANT_INSTALLATION.md) et s'exécute depuis `apps/symfony`, **après** `composer install` et les migrations de la démonstration.

## Périmètres et chemins

| Jeu de données | Fichier utilisé par Populorum | Distribution |
| --- | --- | --- |
| Simulations fictives | `var/populorum.sqlite` | Migrations Symfony, indépendante |
| INSEE COG 2026 + codes postaux La Poste | `data/geography/communes-2026.sqlite` | Inclus en `.gz` dans le code ; fichier SQLite proposé sur la release nationale |
| DECP national, contrats publics | `data/research/public-procurement.sqlite` | Trois parties indépendantes, réunies et validées après téléchargement |
| SSMSI national, délinquance communale | `data/research/ssmsi-national-2025.sqlite` | Import facultatif de la source officielle 2026, années 2024–2025 | 
| Budgets communaux OFGL | `data/research/municipal-national-2025.sqlite` | Import distinct ; non fourni dans la release DECP |

Une fiche communale ne prouve ni l'existence de comptes budgétaires disponibles ni un paiement. Les montants DECP sont contractuels, pas des dépenses acquittées. Les index sont consultés en lecture seule et n'écrasent jamais la base de simulation.

## 1 — Installer le référentiel INSEE immédiatement, hors réseau

L'archive géographique est déjà dans les sources officielles de l'application. Aucun téléchargement supplémentaire n'est nécessaire :

```bash
cd apps/symfony
php bin/console app:geography:install --env=demo
php bin/console app:geography:install --check --env=demo
```

Le fichier vérifié pour la distribution nationale contient **34 875 communes** et **35 313 correspondances postales** (millésime 2026, `PRAGMA quick_check=ok`). Son SHA-256 est :

```text
40449a3c1bd346d83f4330b1a6dc6486ecdd6942ff34a01e19c38ce12d247b82
```

## 2 — Installer éventuellement le fichier INSEE de la release

Release de données : [data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026).

**Cette release contient la base INSEE officielle vérifiée.** Sans accès au réseau, utiliser l'archive incluse (section 1).

```bash
# Depuis apps/symfony
DATA_TAG=data-national-2026
DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/$DATA_TAG"
mkdir -p data/geography
curl -fL --retry 3 -o /tmp/populorum-communes-2026.sqlite "$DATA_URL/communes-2026.sqlite"
printf '%s  %s\n' '40449a3c1bd346d83f4330b1a6dc6486ecdd6942ff34a01e19c38ce12d247b82' '/tmp/populorum-communes-2026.sqlite' | sha256sum -c -
if test -e data/geography/communes-2026.sqlite; then
  echo "Référentiel INSEE déjà présent ; conservation de la base existante."
else
  install -m 0644 /tmp/populorum-communes-2026.sqlite data/geography/communes-2026.sqlite
fi
php bin/console app:geography:install --check --env=demo
```

Si une base géographique est déjà installée, ne pas la remplacer sans sauvegarde et décision explicite.

## 3 — Publier DECP en trois parties, sans script temporaire

L'envoi navigateur de la base de ~1,7 Go s'interrompt. Le transfert s'effectue en trois fragments binaires créés par les commandes natives sqlite3, split et sha256sum. **Aucun script de découpage n'est inclus dans le dépôt.**

### A. Préparer les cinq fichiers sur Arch Linux

Arrêter le serveur PHP et les autres processus qui écrivent dans la base. Depuis apps/symfony :

~~~bash
sudo pacman -S --needed sqlite coreutils github-cli

SOURCE="data/research/public-procurement.sqlite"
WORK="$HOME/Archives/Populorum/decp-snapshot"
OUT="$HOME/Archives/Populorum/decp-2026-parts"

test -f "$SOURCE" || { echo "Base DECP introuvable"; exit 1; }
mkdir -p "$WORK" "$OUT"
test ! -e "$WORK/public-procurement.sqlite" || {
  echo "Snapshot déjà existant ; ne pas écraser"
  exit 1
}
test -z "$(find "$OUT" -mindepth 1 -maxdepth 1 -print -quit)" || {
  echo "Dossier de fragments non vide ; ne pas mélanger les versions"
  exit 1
}

# Snapshot transactionnellement cohérent, notamment en présence d'un journal WAL.
sqlite3 "$SOURCE" ".backup '$WORK/public-procurement.sqlite'"
test "$(sqlite3 -readonly "$WORK/public-procurement.sqlite" 'PRAGMA quick_check;')" = "ok" || exit 1
test "$(sqlite3 -readonly "$WORK/public-procurement.sqlite" \
  "SELECT value FROM metadata WHERE key='schema';")" = "populorum-decp-1" || exit 1

split -n 3 -d -a 2 "$WORK/public-procurement.sqlite" \
  "$OUT/public-procurement.sqlite.part-"

(
  cd "$OUT" || exit 1
  sha256sum public-procurement.sqlite.part-?? > SHA256SUMS-DECP.txt
  sha256sum -c SHA256SUMS-DECP.txt
)
(cd "$WORK" && sha256sum public-procurement.sqlite) > "$OUT/public-procurement.sqlite.sha256"

EXPECTED="$(cut -d ' ' -f1 "$OUT/public-procurement.sqlite.sha256")"
ACTUAL="$(cat "$OUT"/public-procurement.sqlite.part-{00,01,02} | sha256sum | cut -d ' ' -f1)"
test "$EXPECTED" = "$ACTUAL" || { echo "Fragments non conformes au snapshot"; exit 1; }
ls -lh "$OUT"
~~~

**Cinq assets à publier** : public-procurement.sqlite.part-00, public-procurement.sqlite.part-01, public-procurement.sqlite.part-02, SHA256SUMS-DECP.txt et public-procurement.sqlite.sha256.

Le snapshot reste **exclusivement sur le PC**, dans le dossier decp-snapshot, et n'est pas téléversé.

### B. Envoi sur la release GitHub

Ouvrir [Modifier la release nationale](https://github.com/PsiGuardian/Populorum/releases/edit/data-national-2026). Déposer uniquement les cinq fichiers du dossier decp-2026-parts. Si le navigateur échoue, utiliser GitHub CLI :

~~~bash
gh auth login
gh release upload data-national-2026 \
  "$HOME/Archives/Populorum/decp-2026-parts/"* \
  --repo PsiGuardian/Populorum
~~~

Ne pas utiliser --clobber. Lors d'une interruption, envoyer uniquement les assets manquants, sans écraser ceux déjà vérifiés.

### C. Restauration sur une autre installation

Après la publication effective des cinq assets, depuis apps/symfony :

~~~bash
sudo pacman -S --needed sqlite coreutils curl

DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026"
CACHE="var/decp-release-cache/data-national-2026"
DB="data/research/public-procurement.sqlite"
TEMP="$DB.restoring"
mkdir -p "$CACHE" data/research

test ! -e "$DB" && test ! -e "$TEMP" || {
  echo "Base ou restauration déjà présente ; aucun écrasement"
  exit 1
}

for asset in \
  public-procurement.sqlite.part-00 \
  public-procurement.sqlite.part-01 \
  public-procurement.sqlite.part-02 \
  SHA256SUMS-DECP.txt \
  public-procurement.sqlite.sha256
do
  curl -fL --retry 3 -C - -o "$CACHE/$asset" "$DATA_URL/$asset" || exit 1
done

(cd "$CACHE" && sha256sum -c SHA256SUMS-DECP.txt) || exit 1
cat "$CACHE"/public-procurement.sqlite.part-{00,01,02} > "$TEMP"

EXPECTED="$(cut -d ' ' -f1 "$CACHE/public-procurement.sqlite.sha256")"
printf '%s  %s\n' "$EXPECTED" "$TEMP" | sha256sum -c - || exit 1
test "$(sqlite3 -readonly "$TEMP" 'PRAGMA quick_check;')" = "ok" || exit 1
test "$(sqlite3 -readonly "$TEMP" \
  "SELECT value FROM metadata WHERE key='schema';")" = "populorum-decp-1" || exit 1

# Publication atomique sans écrasement, sur le même système de fichiers.
ln "$TEMP" "$DB" && rm "$TEMP"
python3 tools/decp_pipeline.py status
python3 tools/verify_decp_history.py --db "$DB"
~~~

Les contrôles individuels et global des empreintes SHA-256 précèdent la mise en service. La base SQLite initiale n'est pas modifiée, et une installation déjà présente n'est jamais remplacée.

## 3 bis — Importer les données communales officielles SSMSI pour /convergence

L'atlas utilise les **34 875 communes** INSEE, qu'il y ait ou non des mesures disponibles. Pour renseigner les véritables observations 2024–2025, télécharger le [CSV communal officiel SSMSI, géographie 2026](https://www.data.gouv.fr/datasets/bases-statistiques-communale-departementale-et-regionale-de-la-delinquance-enregistree-par-la-police-et-la-gendarmerie-nationales) (publication du 9 juillet 2026). Depuis `apps/symfony` :

```bash
mkdir -p data/research
curl -fL --retry 3 -C - \
  -o data/research/ssmsi-communes-2026.csv.gz \
  "https://static.data.gouv.fr/resources/bases-statistiques-communale-departementale-et-regionale-de-la-delinquance-enregistree-par-la-police-et-la-gendarmerie-nationales/20260709-115942/donnee-data.gouv-2025-geographie2026-produit-le2026-06-25.csv.gz"

python3 tools/import_ssmsi_national.py \
  --source data/research/ssmsi-communes-2026.csv.gz

php bin/console cache:clear --env=demo --no-debug
```

L'importateur effectue un traitement **en flux** et prépare un index SQLite distinct, limité aux années 2024 et 2025, avec une ligne par commune, année et indicateur. Il conserve l'empreinte SHA-256 exacte du fichier téléchargé, le statut `diff` ou `ndiff` et les effectifs/taux tels que publiés. Une valeur non diffusée est stockée en `NULL`, **jamais en 0**.

Ce traitement n'invente aucune statistique pour une commune ; certaines combinaisons de commune, d'année et d'indicateur restent non diffusées selon les règles de secret statistique du SSMSI. L'archive d'origine et la base générée ne doivent pas entrer dans l'historique Git.

Après publication de l'index national dans la même release (fichier optionnel `ssmsi-national-2025.sqlite`), il est également possible de l'installer sans retraiter le CSV complet :

```bash
cd apps/symfony
DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026"
mkdir -p data/research
curl -fL --retry 3 -o data/research/ssmsi-national-2025.sqlite.download \
  "$DATA_URL/ssmsi-national-2025.sqlite"
curl -fL --retry 3 -o data/research/ssmsi-national-2025.sqlite.sha256 \
  "$DATA_URL/ssmsi-national-2025.sqlite.sha256"
(cd data/research && sed 's/  ssmsi-national-2025.sqlite$/  ssmsi-national-2025.sqlite.download/' ssmsi-national-2025.sqlite.sha256 | sha256sum -c -)
test ! -e data/research/ssmsi-national-2025.sqlite
mv -n data/research/ssmsi-national-2025.sqlite.download data/research/ssmsi-national-2025.sqlite
```

**Vérifier la disponibilité des assets avant cette option** : le workflow GitHub Actions publie ces fichiers à partir du CSV officiel, mais leur création ne doit pas être présumée tant qu'elle n'a pas réussi.


## 4 — Vérifications après installation

```bash
php bin/console app:geography:install --check --env=demo
python3 tools/decp_pipeline.py status
php bin/console cache:clear --env=demo --no-debug
php bin/console debug:router --env=demo
python3 tests/http_smoke.py
APP_ENV=demo APP_DEBUG=0 php -S 127.0.0.1:5412 -t public
```

Ouvrir `http://127.0.0.1:5412/`, `/communes`, `/budget` et la page de marchés publics. Un HTTP 404 **sur toutes les routes** est un problème de routage/serveur distinct de la présence des référentiels.

## 5 — Publication manuelle des données

Les cinq assets DECP doivent être joints à [data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026) à l'aide des commandes de la section 3. Le référentiel INSEE déjà publié reste inchangé. Conserver une copie originale hors GitHub Releases pour garantir la traçabilité et la récupération des données.
