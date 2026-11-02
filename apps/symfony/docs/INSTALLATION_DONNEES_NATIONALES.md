# Installation des données nationales — Populorum

Ce guide complète [LIRE_AVANT_INSTALLATION.md](../../../LIRE_AVANT_INSTALLATION.md) et s'exécute depuis `apps/symfony`, **après** `composer install` et les migrations de la démonstration.

## Périmètres et chemins

| Jeu de données | Fichier utilisé par Populorum | Distribution |
| --- | --- | --- |
| Simulations fictives | `var/populorum.sqlite` | Migrations Symfony, indépendante |
| INSEE COG 2026 + codes postaux La Poste | `data/geography/communes-2026.sqlite` | Inclus en `.gz` dans le code ; fichier SQLite proposé sur la release nationale |
| DECP national, contrats publics | `data/research/public-procurement.sqlite` | Archive unique compressée, avec vérification SHA-256 et contrôle SQLite |
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

## 3 — Installer la base DECP compressée déjà publiée

La base DECP nationale a été téléversée en **une seule archive** dans [GitHub Releases : data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026). Le fichier est nommé public-procurement.sqlite.tar.gz (486 882 207 octets, soit environ 464 Mio). Aucun découpage ni script de manipulation temporaire n'est nécessaire.

Depuis le dossier apps/symfony sur Arch Linux, fermer les éventuels processus d'écriture et exécuter :

~~~bash
sudo pacman -S --needed curl tar sqlite coreutils
mkdir -p var/decp-release-cache data/research

ARCHIVE="var/decp-release-cache/public-procurement.sqlite.tar.gz"
curl -fL --retry 3 -C - -o "$ARCHIVE" \
  "https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026/public-procurement.sqlite.tar.gz"

printf '%s  %s\n' \
  '37d8fd44cbd607d8459ec8f5f6e5a97250c576cca0727fd525106f9e87932f41' \
  "$ARCHIVE" | sha256sum -c -
~~~

L'archive peut contenir un préfixe de répertoire. Repérer son membre SQLite sans extraire tous les autres fichiers :

~~~bash
MEMBER="$(tar -tzf "$ARCHIVE" | grep -E '(^|/)public-procurement\.sqlite$')"
test "$(printf '%s\n' "$MEMBER" | grep -c .)" -eq 1 || {
  echo "L'archive ne contient pas une unique base public-procurement.sqlite"
  exit 1
}

DB="data/research/public-procurement.sqlite"
TEMP="$DB.restoring"
test ! -e "$DB" && test ! -e "$TEMP" || {
  echo "Une base ou une restauration existe déjà : aucun écrasement"
  exit 1
}

tar -xOzf "$ARCHIVE" "$MEMBER" > "$TEMP" || exit 1
test "$(sqlite3 -readonly "$TEMP" 'PRAGMA quick_check;')" = "ok" || exit 1
test "$(sqlite3 -readonly "$TEMP" \
  "SELECT value FROM metadata WHERE key='schema';")" = "populorum-decp-1" || exit 1

ln "$TEMP" "$DB" && rm "$TEMP"
python3 tools/decp_pipeline.py status
python3 tools/verify_decp_history.py --db "$DB"
~~~

Le téléchargement est contrôlé avec l'empreinte SHA-256 du fichier publié. La restauration se fait dans un fichier local temporaire, la base n'est rendue visible qu'après validation SQLite et une base existante n'est jamais écrasée. Conserver les archives JSON officielles si une reconstruction et une analyse de provenance sont nécessaires.

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

L'archive DECP public-procurement.sqlite.tar.gz est déjà en ligne sur [data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026). Conserver séparément une copie de la base originale et les sources JSON afin de préserver la traçabilité.
