# Populorum v0.12.4-clean — dossiers documentaires des marchés publics

Cette livraison est une **archive de sources pour installation neuve**.
Elle conserve l'index DECP v2, les tests et la publication de sources
propres sans bases locales, sans données privées et sans `vendor/`.

## Des marchés traçables de la déclaration à la pièce financière

- Nouvelle fiche documentaire accessible depuis chaque contrat :
  sommaire, identité, historique des publications, autres fiches portant
  la même référence, liens documentaires et déclaration originale ;
- **Une fiche par identité conservatrice de contrat**, avec toutes ses
  publications successives et leurs montants datés, sans en faire autant
  de marchés distincts ;
- Les lots ou versions dont l'identité juridique n'est pas établie sont
  **liés mais non fusionnés**, pour ne pas perdre de contrats réels ;
- **Montants source inchangés** et signaux de contrôle non bloquants pour
  certains montants très élevés et codes postaux atypiques ;
- Formatage des centimes exacts sans passer par les flottants PHP,
  notamment pour des sommes déclarées de 99 999 999 999 € et plus ;
- **Concordance financière facultative** : plafond signé, engagement,
  commandes, factures et paiements, exclusivement à partir de pièces
  citées avec URL précise, date, extrait et périmètre ;
- Aucun rapprochement ne convertit automatiquement la valeur publiée
  dans les DECP en argent réellement payé.

## Données financières facultatives

Un outil indépendant `tools/import_procurement_financial_evidence.py`
installe des pièces financières dans
`data/research/procurement-financial-evidence.sqlite`, fichier local
**distinct** de `public-procurement.sqlite`. Il ne modifie pas la base
nationale, ne réimporte aucun fichier JSON et ne publie aucun montant
fictif.

Les montants réels commandés, facturés et réglés ne sont pas fournis par
les seules DECP. Ils restent indiqués comme non documentés tant qu'une
pièce précise n'est pas ajoutée et contrôlée.

Guide technique :
[PROCUREMENT_DOCUMENTARY_DOSSIERS.md](../apps/symfony/docs/PROCUREMENT_DOCUMENTARY_DOSSIERS.md).

## Tests

- Suite Symfony et inspection Twig ;
- Validation des plans SQLite de recherche nationale ;
- Régression des publications successives et des lots distincts ;
- Centimes sans erreur d'arrondi sur les valeurs extrêmes ;
- Registre financier indépendant, sans écriture dans la base DECP ;
- Installation Arch neuve et publication de l'archive vérifiée.

## Limites explicites

Les contrats de procédure identique peuvent être juridiquement distincts.
Populorum n'en fusionne aucun sans preuve. Les avis BOAMP trouvés par
similarité restent candidats. Une pièce citée dans le registre constitue
une référence à contrôler humainement, pas une certification automatique.
Le montant d'un contrat signé n'est ni une facture ni un paiement.
