# BOAMP national local : import et concordance DECP

Le but est d'éviter **745 076 recherches HTTP individuelles** : un export
officiel BOAMP alimente un registre national SQLite indépendant, puis les
liens DECP↔BOAMP sont calculés localement, hors des requêtes du site.

**Ce système ne prouve aucun paiement.** Les montants déclarés, les
annonces d'attribution et les pièces comptables sont distincts.

## Résultats de lots structurés eForms, sans rapprochement automatique à DECP (v1.5)

Le rapport utilisateur v1.4 du 13 novembre 2026 confirme sur les mêmes
**25 contrats** : **15 candidats locaux**, **1 procédure à identifiants
concordants**, **1 conflit d'objet**, **2 avis d'une autre phase**.
L'avis BOAMP `25-92465` conserve un **indice textuel** pour le lot n°3
à Chessy ; aucune identité contractuelle n'est établie.

Sur le BOAMP officiel, certains avis d'attribution européens utilisent le
format **eForms UBL**. Exemple documenté dans les données publiées par la
DILA : `DONNEES.EFORMS.ContractAwardNotice` peut contenir des chemins
`efac:NoticeResult.efac:LotResult` avec les identifiants
`RES-0001` (résultat), `LOT-0001` (lot), `TEN-0001` (offre),
`CON-0001` (contrat signalé), ainsi qu'un code de résultat.
Source : https://boamp-datadila.opendatasoft.com/explore/dataset/boamp/api/

La version **`boamp-local-v1.5-eforms-award-lot-result-evidence`**
extrait **uniquement des liens structurés documentés** dans la branche
`donnees.EFORMS.ContractAwardNotice.ext:UBLExtensions...efac:NoticeResult.efac:LotResult`.
Les clés UBL sont lues à leur emplacement exact : aucun balayage
descriptions/chaînes arbitraires, aucune reconnaissance implicite
des suffixes numériques, et aucune inférence de bénéficiaire ni de prix.
Les documents ne présentant pas le schéma attendu conservent leurs
classements et aucune relation n'est inventée.

Un résultat observable est conservé dans
`local_decision_history.factors_json.eforms_award_lot_results` :
l'identifiant de l'avis BOAMP, les couples `RES`–`LOT`, leurs éventuelles
références `TEN` et `CON`, le code de résultat brut, le chemin du champ
d'origine et l'empreinte SHA-256 déjà associée à la version de l'avis.
Les drapeaux de prudence précisent :
`identifiers_unique_within_notice_only=true`,
`decp_contract_identity_established=false`,
`lot_award_established_from_this_join=false` et
`invoice_or_payment_established=false`.

**Point fondamental :** un identifiant tel que `LOT-0001` ou
`CON-0001` ne devient PAS une clé nationale reliant des avis
différents. Ces identifiants n'ont de sens que dans le contexte
de leur avis/document. Un avis indiquant « lauréat sélectionné »
ne constitue pas une facture ni une preuve de paiement.
Cette version ne modifie ni le statut d'un candidat ni le
nombre de candidats. Si de telles données structurées existent
dans la copie officielle, leur provenance est affichée comme
un **contexte documentaire interne au BOAMP** ; aucun lien de
marché ni de lot DECP n'est créé sur ce seul fondement.

Ce test est fondé sur des **fixtures eForms représentatives**
et doit encore être confronté aux documents réellement présents
dans le corpus local de l'utilisateur ; il ne présume pas
que l'avis de Chessy dispose de ces champs.

Après installation, recontrôler le même échantillon en lecture seule
sur les données DECP et BOAMP :

```bash
python3 tools/match_boamp_national.py --offset 0 --limit 25 --max-notices-per-buyer 0
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 --local --history --output var/boamp-25-eforms-v1-5.txt
```

Le prochain rapprochement déterministe devra comparer les références
de procédure explicitement publiées entre DECP, avis et résultat
contractuel, en conservant toutes les hypothèses comme non établies
tant qu'il n'existe pas une clé réellement partagée.

## Lots explicitement publiés : indices textuels vérifiables (v1.4)

Le rapport local v1.3 du 13 novembre 2026 confirme **25 contrats,
399 avis consultés lors des contrôles, 15 candidats, une concordance
d’identifiants, un conflit d’objet et deux alertes de phase**. Il reste
16 contrats sans piste retenue, et la couverture BOAMP est `partial`.

La version **`boamp-local-v1.4-lot-title-evidence`** introduit un
premier indice d'identification d'un lot, **sans changer les statuts,
sans importer de nouveaux avis, sans certifier de lot ni de paiement**.

Source et méthode :

1. Dans l'intitulé **`objet`** de l'avis BOAMP officiel tel
   qu'importé et conservé avec son empreinte SHA-256, relever
   uniquement les segments explicites du type
   `Lot n°1 : ... Lot n°2 : ... Lot n°3 : ...`.
   Rejeter les listes ambiguës, les numéros répétés, les libellés
   vides ou démesurés. Ce titre **ne prouve pas l'exhaustivité**
   des lots de l'avis ni une attribution.
2. Dans l'intitulé et la référence **DECP**, ne reconnaître un
   numéro de lot que s'il est **introduit par « lot »**. Par
   exemple `2025300000000000` n'est pas interprété comme un
   numéro ; `Lot 03 - ...` peut déclarer le n°3.
3. S'il existe une mention numérotée de lot des deux côtés, la
   signaler comme **coïncidence documentaire de numéro**, sans
   prouver que la procédure, l'attribution et le titulaire sont
   identiques.
4. Sinon, ne proposer un *lot plausible par titre* que lorsque
   **un seul lot** dépasse 67 % de similarité lexicale Jaccard,
   possède au moins deux mots communs et dépasse le deuxième
   meilleur score d'au moins 20 points. Un titre à plusieurs
   lots est comparé au fragment spécifique de l'intitulé DECP
   avant la séparation « - », si celui-ci est exploitable.
5. Les documents vagues ou contradictoires produisent un état
   `indeterminate_title_only` ou une mention de numéro DECP
   absent de l'intitulé BOAMP : **jamais une preuve négative**.

**Chessy (lot 3)** : DECP mentionne « Pièce d'eau et
aménagements écologiques - Aménagement paysager du parc du château
de Chessy » avec la référence `2025300000000000`. L'avis
BOAMP `25-92465` énumère notamment le « Lot n°3 : Pièce d'eau
et aménagements écologiques ». Le fragment DECP correspond au
titre de ce lot, donc l'algorithme peut afficher **« indice
documentaire de lot BOAMP n°3 »**, *non certifiant*.
Le numéro n'est **pas attesté côté DECP**, l'avis n'atteste pas
l'identité juridique du marché attribué, et aucun paiement ne
peut être déduit. L'avis `25-78808`, sans liste explicite dans
son titre, reste candidat sans numéro de lot inventé.

Les données calculées restent dans `local_decision_history.factors_json`
avec `lot_scope.method`, les numéros et intitulés source, le
fragment DECP comparé, le score calculé et les drapeaux
`legal_lot_identity_confirmed=false`,
`award_or_payment_confirmed=false` et
`advertised_lots_exhaustive=false`. Une mention courte et
explicitement non certifiante est ajoutée au motif visible sur
la fiche et dans l'audit. Les deux sources DECP et BOAMP sont
strictement en **lecture seule**.

Le bilan de l'audit distingue désormais en toutes lettres les
**anciennes recherches API non reclassées** (16 candidats dans
ce jeu d'essai) du **classement BOAMP local actuel**
(15 candidats avant et après ce correctif). Ces chiffres ne
doivent pas être additionnés.

**Étape ultérieure** : recouper les identifiants de lots
structurés présents dans les eForms ou les avis d'attribution,
l'identifiant propre du marché/lot et le titulaire déclaré,
lorsque ces champs existent réellement dans les sources et
portent une référence commune vérifiable. Une correspondance
de titre ne doit jamais devenir une attribution « certifiée »
par simple ajout d'un seuil statistique.

Contrôle sans réseau, sur les **mêmes 25 contrats** :

```bash
python3 tests/decp_boamp_concordance_test.py
python3 tests/boamp_national_match_test.py
python3 tools/match_boamp_national.py --offset 0 --limit 25 --max-notices-per-buyer 0
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 \
  --local --history --output var/boamp-25-lots-v1-4.txt
```

## Périmètre contractuel : travaux vs maîtrise d’œuvre (v1.3)

L'audit des 25 mêmes contrats a mis en évidence **Chessy** :
la DECP signale une opération « Pièce d'eau et aménagements écologiques »
avec le **CPV 45232454-1** (famille 45 : travaux), tandis que les
avis BOAMP **23-45168** et **23-81447** annoncent une **maîtrise
d’œuvre** pour le parc du château de Chessy. Ce sont possiblement
des étapes d'un même programme d'aménagement, mais **un marché de
maîtrise d’œuvre ne constitue pas, à lui seul, le marché de travaux
d'un lot**.

La règle conservatrice **`boamp-local-v1.3-phase-scope-review`**
s'applique uniquement aux résultats auparavant `candidate` :

1. Le contrat DECP comporte un CPV de travaux `45......`, à huit
   chiffres, avec son éventuelle clé de contrôle.
2. L'intitulé BOAMP emploie **explicitement « maîtrise d’œuvre »**.
3. Le DECP ne mentionne pas lui-même la maîtrise d’œuvre ; les
   intitulés mixtes (travaux **et** maîtrise d’œuvre, marché global
   de conception-réalisation) restent indéterminés sur ce point.
4. **Aucune référence DECP exacte n'est citée dans le document
   BOAMP**. Les rapprochements `procedure_ids` demeurent prioritaires.

Le résultat passe de `candidate` à **`phase_conflict`** : un
**avis apparenté à une autre phase**, à contrôler, **hors du compteur
des candidats directs au contrat de travaux**. Cette distinction
n'invalide pas le programme auquel l'avis appartient et ne permet
jamais de prouver une absence de lien. L'avis et son URL restent
dans `local_matches`, visibles dans la fiche publique et l'audit.
La transition est conservée par `local_decision_history`, et les
anciens candidats de `reference_matches` ne sont pas réintroduits
en doublon.

Sur le lot de référence, on attend, **à vérifier localement** :

| Type | Avant | Après |
| --- | ---: | ---: |
| Candidats | 17 | 15 |
| Références de procédure concordantes | 1 | 1 |
| Conflits d'objet | 1 | 1 |
| Phases contractuelles distinctes | 0 | 2 |
| Contrats examinés | 25 | 25 |

Les avis **25-78808** et **25-92465**, relatifs aux aménagements
paysagers et, dans le second, au **lot n° 3 « Pièce d'eau et
aménagements écologiques »**, doivent rester candidats. Une
mention de lot **n'établit pas encore** à elle seule que le lot
attribué dans les DECP correspond à l'avis.

La démarche est **volontairement non exhaustive**. Le CPV lui-même
peut être erroné, des intitulés peuvent réunir plusieurs prestations,
des variantes linguistiques peuvent échapper à ce contrôle, et les
archives BOAMP demeurent `partial`. Les dates DECP aberrantes sont
conservées sans correction ni exclusion chronologique artificielle.
Aucun avis ne valide une facture, un mandat ou un paiement.

Pour recontrôler les 25 contrats sans réseau BOAMP :

```bash
python3 tools/match_boamp_national.py --offset 0 --limit 25 --max-notices-per-buyer 0
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 \
  --local --history --output var/boamp-25-phases-versionnees.txt
```

## Réduction des faux positifs : conflit entre objets centraux de projet

L'audit des 25 contrats a identifié un cas concret à **Tiercé**.
Le DECP concerne un concours pour la création d'un **pôle enfance**.
Le BOAMP `24-48133` porte sur le pôle enfance, tandis que l'avis
`25-14275` porte sur la création d'une **maison de l'économie**.
Le SIRET et plusieurs mots génériques concordent ; le second avis
recevait 50 % de similarité Jaccard, bien qu'il décrive un projet distinct.

La règle **`boamp-local-v1.2-object-conflict-review`** ne modifie
ni les 35 % de seuil Jaccard, ni la preuve `procedure_ids`.
Après avoir déterminé les candidats, le moteur local compare une seule
construction linguistique suffisamment explicite :

- Les deux titres désignent une opération de **création de...**.
- Chacun comporte un objet central composé d'au moins deux termes
  substantiels ; les deux ensembles sont disjoints.
- Les opérations à plusieurs objets, les titres sans ce schéma ou
  comportant des termes centraux communs ne sont pas disqualifiés.
- Une référence contractuelle DECP **explicitement trouvée** dans
  l'avis n'est jamais invalidée par ce contrôle lexical.

La discordance fait passer la publication de `candidate` à
**`object_conflict`**. Le lien n'est **plus compté parmi les
candidats automatiques**, mais l'avis BOAMP, son URL et la
justification sont **conservés et affichés** dans un groupe dédié.
Le journal `local_decision_history` garde la transition
`candidate → object_conflict`, sans inventer d'identité juridique.

Les anciennes recherches individuelles `reference_matches` restent
dans SQLite ; lorsqu'une publication figure aussi dans
`local_matches` avec le statut `object_conflict`, sa vieille
classification de candidat n'est pas réintroduite dans la fiche
publique. L'audit des recherches historiques conserve néanmoins
l'information de son classement antérieur.

**Important :** ce signal est un **conflit lexical à vérifier**,
non une démonstration certaine que les deux avis ne participent à
aucun même programme ou à aucune procédure commune. Les termes
« maison de l'économie » et « pôle enfance » restent consultables
dans leur version originale. Cette règle peut produire des erreurs
et ne valide ni un lot, ni un montant réellement payé.

Tests de non-régression hors ligne : vrai avis pôle enfance,
faux candidat maison de l'économie, conservation de l'historique,
idempotence des deux journaux, variantes de synonymes, projets
multi-objets, titres de phases différentes, Normandie et référence
officielle de procédure. Un contrôle réel sur les **mêmes 25 DECP**
est requis avant toute généralisation.

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
`legacy_snapshot` et 19 `observed`. Les **16 contrats sans candidat**
n'avaient aucune entrée dans le journal des décisions, alors que le
contrôle avait été exécuté. Cette situation n'est pas une preuve
d'absence de lien.

Le nouveau journal distinct **`local_scan_history`** corrige cette
limite sans créer d'« avis BOAMP fictif » pour les recherches vides.
À partir du prochain rapprochement, il conserve pour chaque contrat :

- le nombre d'avis effectivement inspectés et retenus, la limitation
  éventuelle de lecture et l'état de couverture du corpus ;
- la révision BOAMP, la version et le SHA-256 des fichiers de règles ;
- le SHA-256 des entrées DECP utilisées et celui de la liste des avis
  retenus, afin de distinguer modifications de règles, de documents,
  d'entrées et de résultats ;
- la date **réelle de la nouvelle exécution** et un résultat
  `indetermine` lorsqu'aucun candidat n'est retenu, ou
  `pistes_observees` si des pistes documentaires existent.

Deux exécutions identiques (mêmes règles, données d'entrée, révision
BOAMP, couverture et résultats) ne génèrent qu'un seul événement.
Un changement pertinent crée un nouveau relevé, sans écraser
l'ancien. Le journal historique `local_decision_history` est conservé
séparément pour les changements avis par avis.

Le contenu ancien de `local_checks` n'est **pas rétroactivement
présenté comme une exécution de l'algorithme actuel** : le premier
`local_scan_history` ne sera créé qu'au prochain passage réel.
Même si le corpus est marqué `checked`, **un résultat sans candidat
n'est jamais une preuve de non-correspondance**, puisque les documents
non publiés et les autres plateformes peuvent manquer.

Depuis `apps/symfony` :

```bash
python3 tools/match_boamp_national.py --offset 0 --limit 25 --max-notices-per-buyer 0
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 \
  --local --history --output var/boamp-25-scans-versionnes.txt
```

Sur les 25 mêmes contrats, le prochain rapport doit afficher **25
relevés de contrôle** : **9 contrats avec au moins un avis retenu**
(19 avis au total) et **16 contrats sans candidat**. Le détail de chaque décision sur avis demeure accessible
et l'historique des contrôles est également affiché sur les fiches.

## Journal des décisions et traçabilité des versions de l'algorithme

À partir de cette évolution, le moteur local BOAMP **n'écrase plus la
justification des rapprochements sans laisser de trace de son classement**.
La base auxiliaire
`data/research/procurement-auto-concordance.sqlite`
accueille en plus la table **append-only**
`local_decision_history`, créée automatiquement au prochain rapprochement :

- **Identité documentaire** : clé de contrat DECP, identifiant officiel
  BOAMP `idweb`, SHA-256 du document BOAMP retenu, révision du corpus
  et état de sa couverture (`checked` ou `partial`).
- **Identité des règles** : nom de version
  `boamp-local-v1.1-decision-history` et SHA-256 calculé sur les fichiers
  effectifs `reconcile_procurement_boamp.py` et
  `match_boamp_national.py`.
- **Entrées** : SHA-256 des champs de contrat DECP utilisés (SIRET,
  référence, intitulé, date, CPV et lieu déclaré).
- **Motifs** : statut `candidate`, `procedure_ids` ou
  `not_observed`, motif textuel et facteurs JSON réellement calculés :
  SIRET et référence cités, score de Jaccard brut et normalisé, nombre
  de termes communs, seuil, éventuel indice INSEE de nom court.
- **Transitions** : ajout d'un événement à la première observation
  versionnée, lors d'un changement de classement, de règles, d'entrée
  ou de document source ; retrait temporaire de l'avis si une nouvelle
  recherche ne le retrouve pas. Les réexécutions à règles, sources
  et facteurs identiques ne créent pas de doublons de décision.

**Ne jamais assimiler `not_observed` à un rejet certain :** les archives
peuvent être partielles ou absentes. Les changements de version sont
des observations algorithmiques, pas une conclusion juridique sur les
marchés, les lots ou leur paiement.

Les anciens enregistrements `local_matches`, créés avant l'introduction
du journal, restent consultables. À la première réexécution, un
événement `legacy_snapshot` sauvegarde leur statut connu avec la
mention `legacy-unversioned`. **L'algorithme exact et ses facteurs
historiques ne peuvent pas être reconstitués** et ne sont pas inventés.

Contrôler toujours seulement les mêmes 25 contrats de référence :

```bash
python3 tools/match_boamp_national.py --offset 0 --limit 25 --max-notices-per-buyer 0
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 \
  --local --history --output var/boamp-25-historique.txt
```

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
`tools/match_boamp_national.py`, et reste **facultatif**.
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
