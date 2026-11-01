# Budget citoyen et traçabilité des financements publics

## Portée exacte

Ce module enrichit **la page Symfony existante** `/budget-et-priorites`.
Il n'utilise **pas** de montants écrits en dur dans les templates : les chiffres
proviennent de `PublicBudget::cities()` et des fichiers publics OFGL/DGFiP déjà
archivés sous `data/research/`. Les libellés de catégories sont codés dans
`BudgetExplorer::CATEGORIES` ; ce sont des règles de classement, pas des valeurs.
Les justificatifs liés sont lus depuis `data/research/public-funding-links.json`.

En l'état, **aucun financement individuel n'est préenregistré** : `records: []`.
Cette absence signifie « non documenté ici », jamais « la dépense est nulle ».
Ni une mise à jour régulière des données officielles, ni une vérification
automatique de la pièce citée ne sont simulées. Le site reste une démonstration,
mais les agrégats 2025 sont issus de sources officielles archivées.

## Trois niveaux de chiffres et leurs limites

1. **Budget global / agrégats OFGL 2025** : dépenses, fonctionnement, investissement,
   personnel, achats, interventions, etc. Ce sont des comptes consolidés, pas des factures.
2. **Répartition par nature** : le filtre « Détail des dépenses sans double comptage »
   affiche neuf rubriques, et vérifie que leur somme retrouve le total de l'exercice
   (tolérance 0,01 €). Les autres filtres peuvent montrer des sous-ensembles qui
   s'emboîtent ou des stocks de dette : on ne les additionne pas.
3. **Flux individualisés publiquement documentés** : décisions, subventions,
   versements et comptes rendus d'emploi, si des documents sourcés ont été ajoutés
   au registre local. L'existence d'une URL ne garantit pas que son contenu vérifie
   la nature ou la réalité d'un paiement : le mainteneur doit lire chaque pièce.

**Point essentiel :** les impôts individuels sont mutualisés. Une continuité
visuelle entre « ressources citoyennes » et « bénéficiaire final » ne démontre
pas que l'euro d'une personne a payé telle dépense. On représente donc la chaîne
*des justificatifs*, pas une traçabilité physique d'une unité monétaire.

## Registre de financement (v1)

Chemin : `apps/symfony/data/research/public-funding-links.json`.

Exemple **fictif de documentation** : ne pas publier cet exemple comme fait réel.

```json
{
  "schema": "populorum-funding-links-v1",
  "records": [
    {
      "id": "exemple_attribution_2025",
      "cityCode": "75056",
      "category": "grants",
      "stage": "awarded",
      "destination": "unknown",
      "amountCents": 125000,
      "documentDate": "2025-05-12",
      "sourceUrl": "https://example.org/deliberation-de-demonstration",
      "from": "Collectivité fictive",
      "to": "Association fictive",
      "parentId": "",
      "purpose": "Attribution pédagogique non réelle",
      "reference": "EXEMPLE-NON-REEL"
    },
    {
      "id": "exemple_paiement_2025",
      "cityCode": "75056",
      "category": "grants",
      "stage": "paid",
      "destination": "unknown",
      "amountCents": 120000,
      "documentDate": "2025-07-01",
      "sourceUrl": "https://example.org/mandatement-de-demonstration",
      "from": "Collectivité fictive",
      "to": "Association fictive",
      "parentId": "exemple_attribution_2025",
      "purpose": "Paiement fictif distinct de l'attribution",
      "reference": "EXEMPLE-NON-REEL"
    }
  ]
}
```

Ne pas copier cet exemple dans le registre réel : il ne correspond à aucune
pièce authentifiée. Renseigner seulement des faits publics consultés et datés.

### Champs

| Champ | Sens |
| --- | --- |
| `id` | Identifiant unique `[a-zA-Z0-9_-]` (max. 64 caractères). |
| `cityCode` | `75056` Paris, `13055` Marseille, `76351` Le Havre. |
| `category` | Domaine principal, clé existante dans `BudgetExplorer::CATEGORIES`. |
| `themes` | Domaines complémentaires facultatifs (liste de clés) ; une action peut relever de plusieurs sujets. |
| `stage` | `voted` (voté), `awarded` (attribué), `paid` (payé), `spent` (emploi justifié). |
| `destination` | `france`, `outside_france`, `unknown`. **Ne pas déduire une destination du seul siège social.** |
| `amountCents` | Montant entier positif en centimes, correspondant à **l'étape** documentée. |
| `documentDate` | Date civile `YYYY-MM-DD` figurant sur la pièce. |
| `sourceUrl` | URL HTTPS de la pièce publique et précisément pertinente. |
| `from`, `to` | Organismes qui interviennent dans le flux, selon la pièce. |
| `parentId` | Étape précédente **expressément reliée** par des documents. Laisser vide si le lien n'est pas établi. |
| `purpose` | Objet littéral documenté, sans inférence. |
| `reference` | Référence de délibération, convention ou compte rendu. |
| `beneficiaryIdentifier` | Identifiant SIREN/RNA documenté, facultatif. |
| `destinationPlace` | Pays/territoire d’utilisation précisément établi par la pièce, facultatif. |

### Exemple de chaîne documentaire réelle à constituer

- Impôt local : **recette globale**, pas mouvement individuel traçable.
- Vote du budget : décision, **pas un versement**.
- Délibération attribuant une subvention : **attribution**, pas paiement.
- Pièce de mandatement ou de versement publiable : **paiement attesté**.
- Compte rendu financier d'une action ou du bénéficiaire : **utilisation décrite**.
- Transfert vers un sous-traitant ou une association partenaire : **nouvelle pièce** indispensable.
- En l'absence d'une de ces pièces, afficher **« Chaîne interrompue »**.

Ne pas publier d'identifiants bancaires personnels, de dossiers non publics,
de coordonnées privées, ni de bénéficiaires physiques identifiables sans base légale.

## Classements sensibles

Les domaines *sécurité*, *action politique*, *financements hors de France*,
*aide à des résidents étrangers*, *culture*, *santé*, *éducation* et *environnement*
restent non chiffrables sur la seule base des balances par nature.
La recherche *financements hors de France* inclut les enregistrements dont l’utilisation hors France a été documentée, même si leur domaine principal est une subvention. **La destination géographique, la nationalité des personnes et l'objet
juridique d'une dépense ne sont pas interchangeables.** Pour les renseigner,
il faut des documents ventilés : budgets par fonction, annexes, délibérations,
conventions d'objectifs, open data de subventions, factures si publiques et comptes
rendus financiers.

Les sources sont à examiner sur [OFGL](https://data.ofgl.fr/),
[data.gouv.fr](https://www.data.gouv.fr/) et sur les sites des collectivités.

## Installation locale

Depuis le dossier décompressé du patch :

```bash
python3 install_local.py /chemin/vers/Populorum
# ou : python3 install_local.py /chemin/vers/Populorum/apps/symfony
```

Le script ajoute deux services PHP et un template Twig à Symfony, ajoute le
chargement CSS et l'appel au nouveau bloc dans la page budgétaire. Les trois
fichiers existants modifiés sont sauvegardés en dehors du dépôt.

```bash
cd /chemin/vers/Populorum/apps/symfony
php -l src/Controller/DemoController.php
php -l src/Service/BudgetExplorer.php
php -l src/Service/BudgetTrace.php
php bin/console lint:twig templates/partials/budget-explorer.html.twig
php bin/console cache:clear
# Si PHPUnit est installé dans le projet :
php bin/phpunit tests/BudgetExplorerTest.php tests/BudgetTraceTest.php
```

L'installateur est ré-exécutable. Il ne commit pas, ne pousse rien sur GitHub,
ne change pas le schéma SQLite et ne nécessite aucune dépendance Composer.
Le registre existant est **conservé** en cas de réinstallation.

### Limites connues et suite logique

- L'index de 4 322 balances DGFiP préexistant reste inchangé.
- Les subventions individualisées ne sont **pas** automatiquement téléchargées ; le
  registre v1 est édité par les mainteneurs et publiquement auditable.
- Pour couvrir toutes les associations nationales, il faudra importer des jeux de
  données supplémentaires, gérer les SIREN/RNA, les doublons et conventions,
  les périodes, les multi-financements et les destinataires intermédiaires.
- Un premier tableau de subventions peut être public sans contenir le **dernier
  emploi** des fonds ; ce dernier exige des comptes rendus documentés.
- Un système de validation des pièces, de révisions et de dates de contrôle est
  recommandé avant de qualifier une trace de « vérifiée ».
