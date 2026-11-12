# BOAMP national local : import et concordance DECP

Le but est d'éviter **745 076 recherches HTTP individuelles** : un export
officiel BOAMP alimente un registre national SQLite indépendant, puis les
liens DECP↔BOAMP sont calculés localement, hors des requêtes du site.

**Ce système ne prouve aucun paiement.** Les montants déclarés, les
annonces d'attribution et les pièces comptables sont distincts.

## Sources

DILA : https://www.boamp.fr/pages/donnees-ouvertes-et-api/
API officielle : https://boamp-datadila.opendatasoft.com/explore/dataset/boamp/api/
Export national JSONL :
https://boamp-datadila.opendatasoft.com/api/explore/v2.1/catalog/datasets/boamp/exports/jsonl

Conserver l'attribution à la DILA, l'URL et la date de chaque export.
Les avis peuvent contenir des données personnelles ; la publication
d'une copie brute doit respecter les conditions de réutilisation.

## Bases distinctes

| Base | Usage |
|---|---|
| `public-procurement.sqlite` | DECP national **en lecture seule** |
| `boamp-national.sqlite` | Avis officiels BOAMP, versions, sources, SIRET cités |
| `procurement-auto-concordance.sqlite` | Liens documentaires recalculés, anciens liens conservés |

Les avis sont identifiés par `idweb`. Toute version distincte est conservée
compressée avec SHA-256. Les rectificatifs et avis antérieurs sont indexés.
Les montants des lots ne sont jamais additionnés automatiquement.

## Installation et premier import (Arch Linux)

Depuis `apps/symfony`, sans dépendance Python supplémentaire :

```bash
df -h .
python3 tools/import_boamp_national.py --download --probe
python3 tools/import_boamp_national.py --stats
```

Selon l'espace disque, choisir **l'un** des deux imports :

```bash
# Publications depuis 2024
python3 tools/import_boamp_national.py --download --since 2024-01-01

# OU historique intégral accessible dans le jeu BOAMP
python3 tools/import_boamp_national.py --download --full
```

**Important :** `--probe`, `--since` et `--file` peuvent produire un index
**incomplet**. Même si ces commandes réussissent, une fiche DECP sans avis
associé doit rester **indéterminée**. Seul un import `--download --full`
sans avis ignoré établit le marqueur de couverture nationale complète.
Un export contenant des URL non officielles ou des IDWEB invalides
reste consultable partiellement, mais son journal d'import porte
`status=partial`. Aucun rapprochement négatif n'en découle.

L'import est **en streaming** : pas de chargement complet du BOAMP
en mémoire. Les transactions sont validées par lots ; un second passage
déduplique les avis et conserve leurs versions différentes.

Le volume national peut être important. L'importeur affiche sa
progression toutes les 5 000 annonces et exige au moins **2 Gio libres**
sur le disque ; `--min-free-gb 5` permet d'augmenter cette réserve.
Une interruption conserve les lots validés. Relancer la commande
réutilise la base existante, mais **le flux HTTP redémarre au début**
(ce n'est pas une reprise des octets téléchargés).

Pour un JSONL déjà téléchargé :

```bash
python3 tools/import_boamp_national.py --file /chemin/boamp.jsonl
python3 tools/import_boamp_national.py --file /chemin/boamp.jsonl.gz
```

## Rattrapage historique ciblé : audit des 25 marchés

**Le JSONL non filtré de la vue OpenDataSoft ne constitue pas forcément
l'ensemble des archives historiques DILA.** Un export observé le 12 novembre
2026 contenait surtout des avis 2026 et seulement une petite portion de 2025.
Il était marqué `partial` : 13 avis connus sur 17 manquaient à la base
locale. On ne doit ni les déclarer inexistants ni évaluer la qualité du
moteur de rapprochement sur ce corpus lacunaire.

Pour valider uniquement les **25 mêmes marchés DECP**, il est possible de
demander **15 avis déjà identifiés** (les 13 manquants des anciens
rapprochements, plus deux avis normands retrouvés par contre-recherche).
L'option `--ids` interroge chaque identifiant BOAMP via l'API officielle,
à raison d'au plus **50 IDWEB par invocation**, avec une pause entre
requêtes ; elle ne lance **aucune requête par contrat DECP** et n'écrit que
dans `boamp-national.sqlite`.

```bash
python3 tools/import_boamp_national.py --ids \
  24-48133 25-14275 23-45168 23-81447 25-78808 25-92465 \
  23-174016 23-62572 24-94095 24-1573 23-139287 \
  24-129485 25-19472 24-31681 24-80963

python3 tools/match_boamp_national.py --offset 0 --limit 25
python3 tools/audit_procurement_concordance.py \
  --offset 0 --limit 25 --local \
  --output var/boamp-audit-local-25.txt
```

L'option `--ids` est un **outil de diagnostic et de rattrapage borné**.
Un avis absent de l'API ponctuelle peut rester accessible dans les archives
DILA : aucun cas n'est classé « sans lien » sur cette base.
Les imports ciblés ne font jamais passer la couverture historique à
`complete`, même si tous les ID ont répondu.

Pour la couverture nationale historique, traiter ultérieurement les archives
officielles **par année/lot ou par période**, et non faire une requête pour
chacun des 745 076 marchés DECP. Archives DILA :
https://echanges.dila.gouv.fr/OPENDATA/BOAMP/FluxHistorique/Boamp_v240/
(2022–2025, distinctes de l'export courant). Leur XML n'est pas le même
format que le JSONL OpenDataSoft ; prévoir un import dédié avec preuve de
provenance, validation du schéma et journal des avis ignorés.

## Concordance locale, sans aucun appel réseau

```bash
# Tester les premières fiches
python3 tools/match_boamp_national.py --limit 25 --offset 0

# Traiter par blocs, avec curseur de reprise indépendant du BOAMP HTTP
python3 tools/match_boamp_national.py --resume --limit 1000

# Revoir le dossier Chessy identifié dans l'audit
python3 tools/match_boamp_national.py \
  --contract-key 7d5dca3c1bd783b4073827a85095310982dfaeb6
```

Pour traiter **toutes** les fiches du périmètre local en conservant la
progression, sans lancer plusieurs processus simultanés :

```bash
mkdir -p var/log
(
  flock -n 9 || exit 1
  while :; do
    python3 tools/match_boamp_national.py --resume --limit 5000 \
      >> var/log/boamp-local-match.log 2>&1
    position=$(sqlite3 data/research/procurement-auto-concordance.sqlite \
      "SELECT value FROM metadata WHERE key='local_next_offset';")
    printf 'Curseur de rapprochement : %s\n' "$position"
    test "$position" = "0" && break
  done
) 9>var/boamp-local-match.lock
```

Les SIRET extraits sont **tous les SIRET cités dans les avis**, pas une
preuve que chacun est celui de l'acheteur. Un même marché peut avoir
plusieurs lots ; toute relation ambiguë reste candidate.

Le traitement des très gros acheteurs est borné à 4 000 avis citant leur
SIRET par défaut. Si ce plafond est atteint, la fiche indique
**recherche partielle** : ce n'est pas une conclusion d'absence de lien.
Pour les très gros acheteurs, utiliser `--max-notices-per-buyer 0` pour
**examiner tous les avis indexés** sans limite numérique par acheteur
(au prix d'une consommation mémoire supplémentaire), ou auditer les
avis officiellement. Une recherche sans plafond local n'est exhaustive
que par rapport à la **couverture réelle de l'index BOAMP**.

Les `local_checks` affichent `partial` si le corpus initial ne provient
pas d'un export intégral officiel réussi, **même quand le seuil de
4 000 avis n'a pas été atteint**. Le champ `truncated` signale,
indépendamment, le plafond atteint chez un acheteur.

## Actualisations incrémentales

Chaque actualisation relit une fenêtre de 14 jours pour récupérer les
annonces récentes, sans effacer les anciennes :

```bash
python3 tools/import_boamp_national.py --download \
  --since "$(date -d '14 days ago' +%F)"
```

Après l'import, lancer de nouveau le rapprochement local des fiches
concernées. La fenêtre glissante **ne garantit pas la détection d'une
correction tardive** d'un vieil avis ; un export historique occasionnel
reste nécessaire pour un corpus strictement à jour.

## Contrôles

```bash
python3 tools/import_boamp_national.py --stats

sqlite3 -readonly data/research/procurement-auto-concordance.sqlite \
  "SELECT status, COUNT(*) FROM local_checks GROUP BY status;
   SELECT status, COUNT(*) FROM local_matches WHERE active=1 GROUP BY status;"
```

Les anomalies de dates DECP (par exemple 6666, 5054, 3036) sont
conservées et ne doivent pas suffire à éliminer une piste. Un avis
BOAMP n'est **pas** une preuve de paiement ou un plafond signé
pour un lot donné.

Les tests couvrent l'import JSONL, les révisions, l'idempotence,
les lots distincts, la lecture Symfony et un échantillon exporté
depuis l'API DILA officielle.
