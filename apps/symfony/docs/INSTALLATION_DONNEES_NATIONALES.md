# Installation des données nationales — Populorum

Ce guide complète [LIRE_AVANT_INSTALLATION.md](../../../LIRE_AVANT_INSTALLATION.md) et s'exécute depuis `apps/symfony`, **après** `composer install` et les migrations de la démonstration.

## Périmètres et chemins

| Jeu de données | Fichier utilisé par Populorum | Distribution |
| --- | --- | --- |
| Simulations fictives | `var/populorum.sqlite` | Migrations Symfony, indépendante |
| INSEE COG 2026 + codes postaux La Poste | `data/geography/communes-2026.sqlite` | Inclus en `.gz` dans le code ; fichier SQLite proposé sur la release nationale |
| DECP national, contrats publics | `data/research/public-procurement.sqlite` | Fichier SQLite unique d'environ 1,7 Go, ajouté manuellement à la même release |
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

## 3 — Installer l'index national DECP (un seul fichier de 1,7 Go)

Le fichier `public-procurement.sqlite` doit être **ajouté manuellement** aux assets de la release [data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026), accompagné de `public-procurement.sqlite.sha256`. **Il n'est pas encore garanti disponible** : contrôler sa présence sur la page GitHub avant de lancer ces commandes.

La taille d'un asset GitHub Releases doit rester strictement inférieure à 2 Gio (2 147 483 648 octets). Avant l'envoi, vérifier aussi le schéma attendu `populorum-decp-1` et l'intégrité SQLite. Ne pas importer un ancien index au format incompatible.

```bash
# Depuis apps/symfony ; laisse le serveur web arrêté
DATA_URL="https://github.com/PsiGuardian/Populorum/releases/download/data-national-2026"
mkdir -p data/research
test ! -e data/research/public-procurement.sqlite || { echo "Base DECP existante : aucun écrasement." >&2; exit 1; }

curl -fL --retry 3 -C - -o data/research/public-procurement.sqlite.part "$DATA_URL/public-procurement.sqlite"
curl -fL --retry 3 -o data/research/public-procurement.sqlite.sha256 "$DATA_URL/public-procurement.sqlite.sha256"

# Contrôle cryptographique obligatoire avant toute mise en service.
(cd data/research && sed 's/  public-procurement.sqlite$/  public-procurement.sqlite.part/' public-procurement.sqlite.sha256 | sha256sum -c -)

python3 - <<'PY'
from pathlib import Path
import sqlite3
path = Path("data/research/public-procurement.sqlite.part")
with sqlite3.connect("file:" + str(path.resolve()) + "?mode=ro", uri=True) as db:
    assert db.execute("PRAGMA quick_check").fetchone()[0] == "ok"
    schema = db.execute("SELECT value FROM metadata WHERE key='schema'").fetchone()
    assert schema and schema[0] == "populorum-decp-1", schema
    print("DECP : schéma valide ; contrats =", db.execute("SELECT COUNT(*) FROM contracts").fetchone()[0])
PY

mv -n data/research/public-procurement.sqlite.part data/research/public-procurement.sqlite
python3 tools/decp_pipeline.py status
python3 tools/verify_decp_history.py --db data/research/public-procurement.sqlite
```

Le fichier `.sha256` à publier doit contenir une ligne au format : `<empreinte SHA-256>  public-procurement.sqlite`. **Ne pas inventer l'empreinte : elle sera calculée sur le fichier effectivement publié.**

Si les archives ne sont pas encore présentes, la voie alternative officielle reste :

```bash
python3 tools/decp_pipeline.py download
python3 tools/decp_pipeline.py import
python3 tools/decp_pipeline.py verify
# Arrêter ensuite le site web avant publication :
python3 tools/decp_pipeline.py publish --site-stopped
python3 tools/verify_decp_history.py --db data/research/public-procurement.sqlite
```

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

## 5 — Où publier la base de 1,7 Go ?

1. Ouvrir https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026
2. Cliquer sur **Edit** (droits mainteneur) puis déposer `public-procurement.sqlite` dans **Attach binaries**.
3. Sur le poste qui détient la base, calculer `sha256sum public-procurement.sqlite > public-procurement.sqlite.sha256` et ajouter aussi ce fichier comme asset.
4. Enregistrer la release, puis vérifier que les deux noms d'assets sont exacts et tester le lien public.

Ne pas ajouter cette base au dépôt Git, ni la déplacer vers `public/`. Éviter d'écraser une base existante. Les exports et sources officiels restent à conserver indépendamment.
