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
