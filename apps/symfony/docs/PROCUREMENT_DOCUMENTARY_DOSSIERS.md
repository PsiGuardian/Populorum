# Dossiers documentaires de marchés publics — identification, preuves et montants

## Règle de conservation

Populorum restitue les données essentielles de la commande publique (DECP)
sans corriger arbitrairement un montant ou une date. Une valeur atypique
demeure consultable, même si elle est manifestement très élevée.

**Une publication ne vaut ni nouveau contrat, ni paiement.**

Dans l'index DECP v2, chaque ligne `contracts` possède un `contract_key`
établi de manière conservatrice. Les nouvelles déclarations identifiées avec
la **même clé** mettent à jour une fiche unique ; les fichiers, empreintes,
observations et versions restent associés à cette fiche.

Lorsqu'une variation de l'objet, de la notification ou du titulaire change
la clé, l'identité juridique n'est pas prouvée. La fiche documentaire permet
désormais de voir les autres lignes ayant le même **acheteur, identifiant
original et nature**. Elles sont intitulées *références apparentées*, sans
les fusionner : plusieurs lots d'une même procédure peuvent effectivement
partager un identifiant initial. Une future consolidation de ces lignes doit
être justifiée par une pièce de lot, un acte signé ou un lien officiel
non ambigu. Ne pas agréger ces variantes en un total.

## Performance : index facultatif de rapprochement pour bases existantes

Depuis v0.12.5-clean, la recherche des autres fiches de même référence
nécessite l'index SQLite `contracts_buyer_reference` sur les colonnes
`(buyer_id, kind, original_id, notice_date DESC, contract_key ASC)`.

- Une **nouvelle installation** le construit automatiquement pendant l'import
  DECP, au même titre que les autres index de recherche.
- Une **base DECP v2 antérieure** reste intégralement consultable sans cet
  index : la liste des références apparentées est seulement suspendue,
  plutôt que de déclencher une recherche lente sur toute la base.
- La chronologie du contrat, les sources et la recherche générale
  continuent de fonctionner.

Pour activer les références apparentées sur une base déjà publiée, il suffit
de construire l'index **une seule fois**, sans aucun réimport ni modification
des montants. Fermer les serveurs PHP qui consultent la base et disposer
d'espace libre avant cette opération SQL :

```bash
cd apps/symfony
sqlite3 data/research/public-procurement.sqlite \
  "PRAGMA busy_timeout=30000;
   CREATE INDEX IF NOT EXISTS contracts_buyer_reference
   ON contracts(buyer_id,kind,original_id,notice_date DESC,contract_key ASC);"
sqlite3 -readonly data/research/public-procurement.sqlite \
  "PRAGMA index_list('contracts');"
```

**Attention :** la création de cet index est une opération ponctuelle
de lecture/écriture sur un gros fichier SQLite. Selon les ressources du
disque et le nombre de contrats, elle peut prendre du temps et nécessite
de l'espace libre. Aucun autre index ni aucune table de données ne doit
être supprimé. Ne pas lancer cette commande si un import ou une publication
DECP est en cours. Ne pas lancer `VACUUM`.

Pour identifier le coût précis en environnement de test, le traceur
`POPULORUM_TIMING=1` rapporte maintenant `decp_related_references`
en plus de `decp_detail` et `decp_search`.

## Fiche documentaire

`/marches-publics/{contract_key}` comprend :
- Identité : référence publiée, acheteur, dates, lieu, CPV et titulaire ;
- Montant source exact et éventuels **points de contrôle** (sans correction) ;
- Concordance financière : contrat signé / plafond, engagement, commandes,
  factures et règlements, **exclusivement lorsqu'une pièce sourcée existe** ;
- Historique des publications : noms, liens précis disponibles, dates,
  montants de chaque observation, occurrences, empreintes SHA-256 ;
- Références apparentées : liens vers des fiches distinctes possiblement
  liées, sans prétention d'identité juridique ;
- Justifications et documents ajoutés à `procurement_evidence`, en séparant
  les liens BOAMP **candidats** des documents cités ;
- Déclaration JSON originale conservée avec son empreinte.

Sur les résultats de recherche, plusieurs publications sous une même
`contract_key` donnent **une fiche, pas plusieurs contrats**. Le compte
des fichiers est affiché. Les montants de publication ne sont jamais sommés.

## Pourquoi des montants de règlement ne figurent pas automatiquement

Les DECP ne prouvent ni paiement, ni montant effectivement commandé, ni
service fait. Les factures individuelles de Chorus Pro et les pièces
comptables d'un établissement ne constituent pas un jeu de données
national public librement accessible par contrat.

Populorum **n'invente aucune donnée manquante**. Une absence de pièce
n'indique pas un paiement nul. Les comptes financiers agrégés d'un hôpital
ne sont pas automatiquement attribuables à un accord-cadre particulier.

## Ajouter des pièces financières justifiées

Une base SQLite **distincte** du référentiel DECP peut stocker des montants
référencés par contrat et document : `data/research/procurement-financial-evidence.sqlite`.
La base DECP nationale demeure en lecture seule, sans migration ni réimport.
La base secondaire est locale et n'est pas publiée dans l'archive Git.

Créer un fichier JSON local qui contient une liste d'objets avec les champs :

| Champ | Description |
|---|---|
| `contract_key` | Clé de 40 caractères du contrat effectivement identifié |
| `stage` | `ceiling`, `committed`, `ordered`, `invoiced`, `paid` |
| `amount_eur` | Montant décimal **en chaîne**, p. ex. `"1234.50"`, sans arrondi binaire |
| `scope` | `cumulative` (cumul), `period` (période), `single` (opération) |
| `as_of_date` | Date d'arrêt du montant au format `AAAA-MM-JJ` |
| `period_start`, `period_end` | Dates de la période ou chaînes vides, obligatoires pour `period` |
| `document_date` | Date du document au format `AAAA-MM-JJ` |
| `source_url` | **URL exacte HTTPS** de la pièce concernée |
| `source_issuer` | Organisme émetteur de la pièce |
| `document_title` | Intitulé exact du document |
| `source_excerpt` | Extrait précis justifiant le chiffre et sa catégorie |

**Ne pas utiliser de sommes illustratives ou d'URL de catalogue général pour
renseigner des chiffres réels.** Le document doit indiquer explicitement le
marché, le périmètre, l'étape et le montant. Une facture ne doit jamais être
convertie automatiquement en paiement. Le script vérifie la structure, la
référence du contrat, les bornes, les centimes et l'URL, mais ne certifie
**pas** le contenu du document : celui-ci doit être vérifié humainement.

Depuis `apps/symfony`, avec un index DECP public installé :

```bash
python3 tools/import_procurement_financial_evidence.py /chemin/documents-verifies.json
```

L'import est idempotent sur les valeurs identiques et transactionnel.
Il refuse les références absentes du registre et les URLs non publiques.
Il n'écrit jamais dans `public-procurement.sqlite`.

Inspecter la base auxiliaire :

```bash
sqlite3 -readonly data/research/procurement-financial-evidence.sqlite "
SELECT stage, amount_cents, accounting_scope, as_of_date,
       document_title, source_url
FROM financial_evidence
ORDER BY as_of_date DESC
LIMIT 30;
"
```

**Ne pas additionner** les lignes de différentes dates, périmètres ou
documents : elles peuvent se recouvrir. Lors de la consultation, la fiche
affiche chaque observation individuellement, son lien et son extrait.

## Cas d'étude : AO25DMISP

Plusieurs fiches associées au CHU Grenoble Alpes présentent un objet
proche et le montant déclaré 99 999 999 999 €. Il est interdit de
conclure qu'il s'agit de contrats identiques ou de paiements réels sur
cette seule base. Les liens entre lots doivent être vérifiés dans les
actes signés et documents d'attribution.

La démarche documentaire consiste à établir :
1. les identifiants exacts des lots et les titulaires ;
2. les montants contractuels et plafonds **par lot** ;
3. les commandes passées, si communicables ;
4. les factures et montants effectivement réglés, si accessibles ;
5. les dates, pièces et rapprochements, avec contradictions conservées.

Un avis BOAMP dont la correspondance est seulement textuelle reste
**candidat** et ne devient jamais une pièce probante par automatisme.

## v0.13.0 : fiche de concordance et avis BOAMP rapprochés automatiquement

La page de détail n'affiche plus un menu de six ancres : elle présente
directement les faits, les étapes documentées et les liens disponibles.
L'observatoire national et la fiche individuelle sont des vues distinctes.
Les sections vides ne produisent pas de faux liens documentaires.

La concordance automatisée utilise exclusivement le **BOAMP officiel**
(avis publiés par la DILA). Les avis sont recherchés en lot hors ligne,
puis reliés à une fiche via un second index SQLite facultatif :
\`data/research/procurement-auto-concordance.sqlite\`.

Le lien vers un avis est qualifié précisément :

- **Identifiants concordants** : le SIRET acheteur à 14 chiffres et la
  référence d'origine du marché apparaissent *tous deux* dans le contenu
  officiel de l'avis. Ceci identifie une **procédure**, pas un lot précis.
- **Piste non confirmée** : même SIRET acheteur et intitulés suffisamment
  proches, mais identifiant original absent. Ces pistes ne justifient
  aucune affirmation sur l'attribution ou sur les montants.
- **Aucun avis établi** : la recherche effectuée peut n'avoir trouvé
  aucune annonce dans les résultats retournés. Cela ne prouve pas
  qu'aucun avis existe.

Si un montant EUR global \`TotalAmount\` est **unique** dans la
déclaration BOAMP, il est affiché séparément comme **montant mentionné
dans l'avis pour son périmètre**. Un montant multi-lots ne doit jamais
être attribué automatiquement à un lot ou assimilé à une facture.
Aucun paiement réel n'est déduit de BOAMP ou des DECP.

Exemples depuis \`apps/symfony\` :

\`\`\`bash
# Une fiche ciblée : recommandée pour les anomalies financières
python3 tools/reconcile_procurement_boamp.py \
  --contract-key cf6d3a11656665868dfd8f105f1a4d17edd5ec38

# Petit lot, limité et ré-exécutable ; pause respectueuse de l'API officielle
python3 tools/reconcile_procurement_boamp.py --limit 10 --offset 0 --pause 1.2
python3 tools/reconcile_procurement_boamp.py --limit 10 --offset 10 --pause 1.2
# Mode récurrent : mémorise automatiquement la prochaine position
python3 tools/reconcile_procurement_boamp.py --resume --limit 25 --pause 1.2

# Derniers contrôles sans exposer les données privées
sqlite3 -readonly data/research/procurement-auto-concordance.sqlite \
  "SELECT status,COUNT(*) FROM checks GROUP BY status;"
\`\`\`

La base DECP nationale est **ouverte exclusivement en lecture seule**,
les fichiers nationaux ne sont jamais téléchargés à nouveau,
et les recherches réseau ne sont jamais faites pendant une visite.
Les réponses des APIs sont bornées et les URL des preuves restreintes
au domaine HTTPS BOAMP. Aucun service de paiement privé n'est
interrogé. Les rapprochements ne sont pas des jugements sur l'acheteur.

Le programme ne balaie pas les ~790 000 marchés à chaque exécution.
Les lots automatiques ont une taille bornée \`--limit 1..50\` et une
position explicite \`--offset\`; une exécution planifiée ou manuelle
peut progresser par tranches sur le serveur ou la machine locale.
Les tâches planifiées peuvent utiliser `--resume` pour mémoriser
automatiquement le prochain lot dans la base auxiliaire, sans retraiter
à chaque passage les premiers contrats de la liste.

Exemple cron (à adapter au chemin réel et aux droits du serveur) :

```cron
15 3 * * * cd /chemin/vers/apps/symfony && /usr/bin/flock -n /tmp/populorum-boamp.lock /usr/bin/python3 tools/reconcile_procurement_boamp.py --resume --limit 25 --pause 1.2 >> var/log/boamp-concordance.log 2>&1
```

Le dossier `var/log` doit être accessible en écriture. Un verrou évite
les exécutions simultanées ; un échec réseau est enregistré, sans
supprimer les références déjà trouvées. Les données ne sont jamais
cherchées au chargement d'une page.

Seules les fiches traitées affichent un historique du contrôle
automatique. Le registre auxiliaire n'est pas inclus dans Git.

## Qualité des correspondances BOAMP (v0.13.3)

Les premières requêtes de v0.13.2 réussissaient techniquement mais pouvaient
renvoyer **0 correspondance** parce qu'elles examinaient les 8 premiers
avis d'une recherche sur des intitulés très génériques. Par exemple, une
recherche « fourniture dispositifs médicaux » renvoie plusieurs milliers
d'avis ; l'absence du contrat parmi les huit premiers ne prouve rien.

Le rapprochement utilise désormais **le SIRET publié dans le contenu BOAMP**
ET un mot distinctif de l'objet dans la clause ODSQL.
Le résultat reste borné à 30 avis par interrogation. Le nombre d'avis
examinés figure dans le journal d'import.

Dans le dossier CHU Grenoble, une recherche combinée du SIRET
`26380030200014` et du terme « implantables » a renvoyé quatre avis
lors des essais du 12 novembre 2026. La référence `AO25DMISP`
n'apparaissait pas dans les avis trouvés ; leur rapprochement
éventuel est une **piste documentaire**, pas une identification
juridique du même lot ni une preuve de règlement.

Les références DECP composées uniquement de chiffres ne sont plus
ignorées par la classification, mais une séquence numérique courte
présente dans un avis ne suffit pas à **confirmer** la référence
juridique : elle peut être un prix ou un autre identifiant.
Les avis peuvent concerner plusieurs lots et d'autres périodes ;
les hypothèses non confirmées restent séparées des concordances.

**Limite actuelle** : une requête ciblée demeure une recherche
partielle sur un nombre borné d'avis. Les contrôles enregistrés avec
`status=ok` signifient seulement que l'API a répondu, non que tous
les avis possibles ont été examinés. Pour viser une couverture
nationale fiable, importer à terme les exports BOAMP hors ligne,
puis effectuer des rapprochements locaux fondés sur les
identifiants et les références des lots, avec historiques.

Après mise à jour, les anciens contrôles à 0 peuvent être recalculés
sans modifier les DECP ni le curseur de reprise :

```bash
python3 tools/reconcile_procurement_boamp.py --limit 25 --offset 0 --pause 1.5
```

## Audit contradictoire des rapprochements BOAMP

Pour vérifier que les **zéros** signalés par l'enrichissement ne masquent
pas des avis pertinents, le programme maintenu
`tools/audit_procurement_concordance.py` confronte la base DECP nationale
et la base auxiliaire de concordance, toutes deux **en lecture seule**.

Il détaille les références DECP, SIRET, dates, objets, montants,
les avis BOAMP liés et leurs URL, puis distingue systématiquement :
- les identifiants rapprochés (procédure, sans preuve du lot) ;
- les simples pistes ;
- les recherches sans avis retenu : **lien inconnu**, jamais
  « aucun lien » ou « aucun marché ».

Pour examiner le premier lot de 25 sans requête réseau :

```bash
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 \
  --output var/boamp-audit-25.txt
```

Pour effectuer une **contre-recherche** des 25 contrats sur l'API
officielle, avec au maximum trois requêtes supplémentaires par fiche :

```bash
python3 tools/audit_procurement_concordance.py --offset 0 --limit 25 \
  --online --output var/boamp-audit-25-online.txt
```

Pour examiner un seul dossier :

```bash
python3 tools/audit_procurement_concordance.py \
  --contract-key c8d232d9ea3928881d2e03c0e31afc3b7f952479 --online
```

Les requêtes alternatives associent le SIRET à la référence originale,
ou à d'autres mots distinctifs de l'intitulé. **Elles aussi sont
bornées à 25 réponses par filtre.** Si l'API indique davantage de
résultats, le rapport l'explicite et ne conclut jamais à l'absence
de relation. Des cas inconclus doivent être examinés avec les actes,
les lots, les titulaires et les dates.

Cet audit ne modifie ni `public-procurement.sqlite`,
ni `procurement-auto-concordance.sqlite`, ni le curseur de reprise.
Il ne contacte aucun service privé et n'affirme jamais que la
correspondance constitue un règlement effectif.

## BOAMP national : rapprochement local plutôt que 745 076 requêtes HTTP

La concordance nationale dispose d'un véritable index BOAMP SQLite
séparé de la base DECP. L'export DILA officiel JSONL est importé en
streaming avec conservation des versions, puis rapproché **localement**
et par lots avec l'index DECP existant.

L'ancienne interrogation par avis reste utilisable pour un cas précis,
mais n'est **plus** la méthode recommandée pour un parcours national.

Documentation et commandes :
[BOAMP_NATIONAL_LOCAL.md](BOAMP_NATIONAL_LOCAL.md).

Aucune base DECP, aucun fichier de vote ou compte citoyen n'est
modifié par ces programmes. Aucun règlement financier n'est déduit
d'un avis de marché.

## Validation

- `phpunit` : formatage sans flottants, liens de même référence sans
  fusion de lots, plusieurs publications d'une seule fiche, chiffres
  documentaires propres à chaque contrat ;
- `python3 tests/decp_financial_evidence_test.py` : sources et dates
  strictes, centimes exacts, import idempotent, lots distincts ;
- `python3 tests/decp_history_test.py` : mises à jour successives
  sans multiplication du contrat indexé.

L'import financier et l'enrichissement documentaire ne sont **pas**
déclenchés lors d'une requête web et ne contactent aucun service privé.
