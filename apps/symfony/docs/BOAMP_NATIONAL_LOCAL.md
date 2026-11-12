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

## Historique des contrôles, y compris sans candidat

Le premier rapport versionné (12 novembre 2026) comportait 38 événements
liés aux **19 avis retenus** parmi **25 contrats examinés** : 19
\`legacy_snapshot\` et 19 \`observed\`. Les **16 contrats sans candidat**
n'avaient aucune entrée dans le journal des décisions, alors que le
contrôle avait été exécuté. Cette situation n'est pas une preuve
d'absence de lien.

Le nouveau journal distinct **\`local_scan_history\`** corrige cette
limite sans créer d'« avis BOAMP fictif » pour les recherches vides.
À partir du prochain rapprochement, il conserve pour chaque contrat :

- le nombre d'avis effectivement inspectés et retenus, la limitation
  éventuelle de lecture et l'état de couverture du corpus ;
- la révision BOAMP, la version et le SHA-256 des fichiers de règles ;
- le SHA-256 des entrées DECP utilisées et celui de la liste des avis
  retenus, afin de distinguer modifications de règles, de documents,
  d'entrées et de résultats ;
- la date **réelle de la nouvelle exécution** et un résultat
  \`indetermine\` lorsqu'aucun candidat n'est retenu, ou
  \`pistes_observees\` si des pistes documentaires existent.

Deux exécutions identiques (mêmes règles, données d'entrée, révision
BOAMP, couverture et résultats) ne génèrent qu'un seul événement.
Un changement pertinent crée un nouveau relevé, sans écraser
l'ancien. Le journal historique \`local_decision_history\` est conservé
séparément pour les changements avis par avis.

Le contenu ancien de \`local_checks\` n'est **pas rétroactivement
présenté comme une exécution de l'algorithme actuel** : le premier
\`local_scan_history\` ne sera créé qu'au prochain passage réel.
Même si le corpus est marqué \`checked\`, **un résultat sans candidat
n'est jamais une preuve de non-correspondance**, puisque les documents
non publiés et les autres plateformes peuvent manquer.

Depuis \`apps/symfony\` :

\`\`\`bash
python3 tools/match_boamp_national.py --offset 0 --limit 25 --max-notices-per-buyer 0
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 \
  --local --history --output var/boamp-25-scans-versionnes.txt
\`\`\`

Sur les 25 mêmes contrats, le prochain rapport doit afficher **25
relevés de contrôle** : **9 contrats avec au moins un avis retenu**
(19 avis au total) et **16 contrats sans candidat**. Le détail de chaque décision sur avis demeure accessible
et l'historique des contrôles est également affiché sur les fiches.

## Journal des décisions et traçabilité des versions de l'algorithme

À partir de cette évolution, le moteur local BOAMP **n'écrase plus la
justification des rapprochements sans laisser de trace de son classement**.
La base auxiliaire
\`data/research/procurement-auto-concordance.sqlite\`
accueille en plus la table **append-only**
\`local_decision_history\`, créée automatiquement au prochain rapprochement :

- **Identité documentaire** : clé de contrat DECP, identifiant officiel
  BOAMP \`idweb\`, SHA-256 du document BOAMP retenu, révision du corpus
  et état de sa couverture (\`checked\` ou \`partial\`).
- **Identité des règles** : nom de version
  \`boamp-local-v1.1-decision-history\` et SHA-256 calculé sur les fichiers
  effectifs \`reconcile_procurement_boamp.py\` et
  \`match_boamp_national.py\`.
- **Entrées** : SHA-256 des champs de contrat DECP utilisés (SIRET,
  référence, intitulé, date, CPV et lieu déclaré).
- **Motifs** : statut \`candidate\`, \`procedure_ids\` ou
  \`not_observed\`, motif textuel et facteurs JSON réellement calculés :
  SIRET et référence cités, score de Jaccard brut et normalisé, nombre
  de termes communs, seuil, éventuel indice INSEE de nom court.
- **Transitions** : ajout d'un événement à la première observation
  versionnée, lors d'un changement de classement, de règles, d'entrée
  ou de document source ; retrait temporaire de l'avis si une nouvelle
  recherche ne le retrouve pas. Les réexécutions à règles, sources
  et facteurs identiques ne créent pas de doublons de décision.

**Ne jamais assimiler \`not_observed\` à un rejet certain :** les archives
peuvent être partielles ou absentes. Les changements de version sont
des observations algorithmiques, pas une conclusion juridique sur les
marchés, les lots ou leur paiement.

Les anciens enregistrements \`local_matches\`, créés avant l'introduction
du journal, restent consultables. À la première réexécution, un
événement \`legacy_snapshot\` sauvegarde leur statut connu avec la
mention \`legacy-unversioned\`. **L'algorithme exact et ses facteurs
historiques ne peuvent pas être reconstitués** et ne sont pas inventés.

Contrôler toujours seulement les mêmes 25 contrats de référence :

\`\`\`bash
python3 tools/match_boamp_national.py --offset 0 --limit 25 --max-notices-per-buyer 0
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 \
  --local --history --output var/boamp-25-historique.txt
\`\`\`

La fiche publique du marché présente un historique borné aux 25 événements
les plus récents ; l'audit CLI aux 40 plus récents par contrat. Les lignes
plus anciennes ne sont pas supprimées de SQLite. Les autres bases
(DECP et BOAMP) sont **uniquement lues**, jamais modifiées.

## Origine des rapprochements : démarche de transparence publique

**Les liens DECP↔BOAMP publiés par Populorum sont des déductions
algorithmiques susceptibles d'erreurs ou d'omissions.** Ils ne certifient ni
l'identité juridique d'un marché/lot, ni l'exécution financière, ni un
paiement. L'absence de candidat n'est jamais une preuve d'absence de lien.

Sources et rôles :

- **DECP** : déclaration de marché par un acheteur public, identifiant DECP,
  SIRET, objet, nature, CPV, date et montant contractuel déclaré. Une valeur
  contractuelle n'est pas une dépense effectivement payée.
- **BOAMP / DILA** : publications et archives officielles ; identifiant
  `idweb`, date de publication, document source, versions et URL de l'avis.
  Un avis peut citer plusieurs SIRET et plusieurs lots.
- **INSEE, COG 2026** : noms officiels de communes et codes INSEE, utilisés
  comme référence pour contrôler l'ambiguïté des variantes de noms.
  Aucun code INSEE ne doit être inventé à partir d'un adjectif ou d'un gentilé.

Algorithme vérifiable dans
[`tools/reconcile_procurement_boamp.py`](../tools/reconcile_procurement_boamp.py) :

1. Validation de la forme du SIRET DECP (14 chiffres), de la référence de
   marché (au moins 6 caractères), du format `idweb` et d'une URL BOAMP
   en HTTPS avec hôte autorisé.
2. Recherche exacte, comme **terme entier**, du SIRET et de la référence
   DECP dans le champ `donnees` de l'avis BOAMP. Un SIRET simplement
   *cité* dans un avis n'est pas nécessairement celui de l'acheteur.
3. Extraction de mots d'au moins 4 caractères des deux intitulés,
   normalisation des accents/casse, suppression de mots génériques,
   normalisation limitée des pluriels et calcul de l'indice de Jaccard
   des ensembles de mots : `|intersection| / |union|`.
4. **Identifiants concordants** (`procedure_ids`) : référence distinctive
   entière et SIRET cités ; une référence purement numérique doit compter
   au moins 10 chiffres et conserver un score Jaccard **brut** d'au moins
   50 % pour éviter de confondre des dates et des montants.
5. **Candidat documentaire** (`candidate`) : SIRET cité, score Jaccard
   morphologique d'au moins **35 %** et **3 termes communs**. Une année
   DECP plausible et un avis distant de plus de 3 ans excluent la piste ;
   une année incohérente (ex. 2926 ou 6666) ne l'exclut pas par elle-même.
6. **Inconnu** : tout autre cas, y compris une source incomplète, un
   avis manquant ou un résultat tronqué. Les pièces BOAMP ne sont pas
   des factures, mandats ou justificatifs de règlement.

Les scores servent à **ordonner des hypothèses documentaires**, pas à
attribuer légalement un marché, déterminer un lot, calculer des paiements
ou émettre des accusations. Les faux positifs (ex. autre projet à Tiercé)
et les faux négatifs (ex. Normandie avant normalisation) restent possibles.
Des modifications successives du moteur impliquent une réévaluation des
liens stockés, avec préservation de l'historique.

## Contrôle exhaustif des noms de communes (COG 2026)

Le cas `normands` / `Normandie` est celui d'un **gentilé / adjectif
régional**, pas d'une variation singulier/pluriel d'un nom de commune.
Le COG INSEE ne fournit pas un dictionnaire exhaustif des gentilés.
Il serait donc dangereux de fabriquer automatiquement des couples du
type `nom-de-commune → adjectif` pour les déclarer équivalents.

Un outil **hors réseau, en lecture seule**, vérifie chaque commune
du référentiel INSEE 2026 déjà installé, sans échantillonnage :

```bash
php bin/console app:geography:install --env=demo
python3 tools/audit_boamp_commune_morphology.py
```

Deux résultats sont produits sous `var/` :

- `boamp-cog-2026-communes.tsv` : **une ligne par commune**, code INSEE,
  nom officiel, clé lexicale, modifications de pluriel, autres communes
  partageant la même clé (homonymies exactes ou créées par normalisation) ;
- `boamp-cog-2026-report.txt` : nombre total de communes vérifiées,
  nombres de cas sensibles et d'ambiguïtés, exemples à étudier.

Par sécurité, cette analyse **n'invente aucune forme officielle ni gentilé**
et ne change pas automatiquement la classe d'un avis BOAMP.
Pour établir une identité territoriale, la référence fiable reste le **code
INSEE explicitement déclaré**, dans le bon millésime. Deux homonymes ne
deviennent jamais la même commune parce que leurs mots normalisés coïncident.
Voir les [tables historiques INSEE](https://www.insee.fr/fr/information/7671867)
pour les fusions et changements de nom.

### Correction opérationnelle : noms de communes courts

L'audit du **COG INSEE 2026** a identifié **229 communes** dont le
nom pris isolément ne produit aucun terme dans le comparateur de titres
BOAMP (<4 caractères ou mots filtrés), dont **173 avec un libellé officiel
normalisé unique** dans le millésime. Les 56 autres comprennent des
homonymes : par exemple neuf communes « Le Pin ». Aucun nom ne
devient un identifiant administratif par simple comparaison textuelle.

Le moteur **local** dispose désormais d'un appoint géographique
**strictement limité aux candidats**. Pour qu'une commune courte puisse
ajouter un seul terme de comparaison, il faut réunir **toutes** les
conditions suivantes :

1. Le référentiel COG 2026 est installé et chargé **en lecture seule**,
   sans téléchargement pendant le rapprochement.
2. Le contrat DECP déclare explicitement un lieu de type
   **« Code commune »**, avec un **code INSEE correspondant**.
3. Ce code renvoie à un **nom court unique** au sein du COG 2026.
   Un homonyme comme « Le Pin » est exclu, même si le code DECP existe.
4. Le nom officiel figure **mot pour mot et à bornes de mots** dans
   les intitulés DECP et BOAMP. L'avis BOAMP doit comporter un contexte
   locatif explicite (par exemple « à Gap » ou « commune de Gap »).
5. Le SIRET de l'acheteur DECP est cité dans le contenu officiel de
   l'avis BOAMP, et les intitulés partagent **déjà au moins deux termes
   substantiels** avant cet appoint géographique.
6. Le score Jaccard final doit toujours atteindre **35 %** avec au
   moins **trois termes communs**. Le nom court n'ajoute qu'un terme.
   Ce signal **n'intervient jamais dans la validation des références**
   de procédure et ne confirme ni le lot ni aucun paiement.

Si l'un des critères manque, le classement est inchangé.
Cette tolérance n'est pas une validation géographique du BOAMP :
le **code INSEE n'y est pas attesté**, il est déclaré côté DECP
et le nom est simplement lu dans un intitulé.

Le catalogue de noms courts est lu au lancement de
\`tools/match_boamp_national.py\`, et reste **facultatif**.
Si la base INSEE n'est pas installée, les rapprochements fonctionnent
comme auparavant, sans bonification géographique.

Tests hors réseau : Gap (commune unique) avec deux termes d'objet
partagés, absence du code INSEE, code postal à la place de code
commune, SIRET erroné, objet différent, absence de contexte géographique,
et « Le Pin » (homonyme). L'ensemble des précédentes concordances
sur 25 contrats reste à examiner après activation locale.

### Correction de l'audit géographique après le premier essai

Le premier contrôle de 34 875 communes (12 novembre 2026) a correctement
dénombré les codes INSEE, mais la première version du rapport annonçait
**5 242 ambiguïtés lexicales** alors qu'elle appliquait aux *noms de communes*
le filtre de mots du titre des marchés : ce filtre ignore les termes de moins
de quatre lettres. Des communes sans rapport, telles que **Bey**, **Boz**,
**Gap** et **Le Pin**, aboutissaient ainsi à la même clé **vide**.

**Ne jamais employer ce premier chiffre pour évaluer les homonymies.**
La version corrigée utilise deux calculs indépendants :

1. **Clé géographique stricte** : tous les mots du nom INSEE sont conservés,
   y compris les mots courts, les articles et les particules. Ils sont
   normalisés (casse, accents et ponctuation), puis triés pour révéler les
   ressemblances, **sans constituer une preuve d'identité**.
2. **Clé de pluriels possible** : cette même clé avec la règle limitée
   de suppression du « s » final, à titre **exploratoire**. Ce traitement
   peut modifier à tort un nom propre (par exemple « Thiers ») et ne
   devient donc **jamais automatiquement un alias de commune**.

Le rapport sépare les homonymes de nom normalisé, les collisions lexicales
*préexistantes*, les ambiguïtés *supplémentaires* introduites par la règle
des pluriels et le nombre de clés géographiques vides (attendu : **zéro**).

Il indique également combien de noms **pris isolément** sont privés de
tous leurs mots distinctifs par le moteur BOAMP actuel, afin de mesurer
la perte d'information de ses filtres. Cela ne signifie pas que l'objet
complet du marché ne contient aucun mot.

Ce contrôle garde **34 875 lignes** si le référentiel INSEE 2026 est complet
et conserve les identifiants officiels. Les données BOAMP/DECP et la méthode
de classement des contrats restent inchangées. Un nouvel audit doit être
lancé pour établir les chiffres corrigés ; ceux-ci ne sont pas inférés du
rapport initial.

## Perspectives Hélios, PES V2 et partenariat DGFiP

Populorum serait enclin à intégrer, **si les autorisations et les exports
nécessaires étaient obtenus** :

| Source | Contenu envisagé | Accès | Portée du rapprochement |
|---|---|---|---|
| **Hélios / PES V2** | Mandats et données de suivi | Organisme habilité ou export légalement obtenu | Potentiellement, avec des références marché exploitables |
| **États de règlement Hélios** | Paiements comptabilisés | Organisme habilité ou export légalement obtenu | Si les liens mandat–marché–règlement sont vérifiables |

**Ces données ne sont pas intégrées à notre rapprochement BOAMP actuel.**
Pour une couverture nationale, un **accès institutionnel centralisé** devra
être étudié auprès de la **DGFiP** (convention, habilitation ou dispositif de
mise à disposition), en limitant les données collectées aux informations
nécessaires et légalement communicables.

Les API sensibles ne sont pas ouvertes au public :
[la DGFiP indique que l'accès est réservé aux partenaires autorisés et
soumis aux conditions DataPass](https://www.impots.gouv.fr/comment-acceder-aux-api-de-la-dgfip).
L'existence de PES V2 ne signifie pas qu'une API nationale des règlements
par marché soit déjà proposée :
[protocole PES V2](https://www.collectivites-locales.gouv.fr/gerer-les-finances-publiques-locales/dematerialisation-des-comptes-locaux-et-open-data/dematerialisation-comptable-et-budgetaire/le-protocole-dechange-standard-pes),
[portail Hélios](https://www.collectivites-locales.gouv.fr/le-portail-helios-au-service-des-collectivites).

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

### Tolérance morphologique des intitulés

Le rapprochement documentaire normalise légèrement les pluriels français
dans **la comparaison des titres** (par exemple `internats` → `internat`,
`chambres` → `chambre`). Le seuil de similarité reste à **35 %**,
et le SIRET de l'acheteur doit toujours apparaître dans le contenu de
l'avis. Les termes utilisés pour interroger l'API officielle ne changent pas.

Ce traitement permet de classer les deux avis normands `24-31681` et
`24-80963` comme **candidats** pour le marché de mobilier d'internat.
**Ce ne sont pas des concordances de procédure confirmées**, car la
référence DECP n'a pas été retrouvée dans les avis. La normalisation
des titres ne peut jamais transformer une référence numérique
ordinaire en preuve d'identité contractuelle.

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
