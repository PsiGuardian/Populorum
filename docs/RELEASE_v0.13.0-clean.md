# Populorum v0.13.0-clean — enquête documentaire des marchés publics

## Nouvelle lecture de chaque contrat

L'observatoire national reste une page de recherche dédiée. Une fiche de
marché s'ouvre désormais directement sur le dossier documentaire,
sans menu d'ancres vides ou liste de résultats parasites.

La fiche expose :
- La **valeur DECP déclarée**, distincte du contrat signé et des règlements ;
- Les pièces signées et paiements référencés, jamais interpolés ;
- Une chronologie de publications conservant chaque version sans multiplier
  artificiellement les contrats ayant la même clé ;
- Les titulaires, références, avenants et documents originaux,
  avec sources précises et SHA-256 ;
- Les références apparentées lorsque l'index optimisé existe ;
- Les rapprochements avec des **avis BOAMP officiels**.

## Concordance officielle hors ligne

Nouveau script `tools/reconcile_procurement_boamp.py` :
- API gratuite DILA / BOAMP, recherche bornée et exécutée explicitement
  **hors requête HTTP**, pour préserver les performances ;
- Exige un SIRET acheteur de 14 chiffres et une référence de marché
  suffisamment distinctive pour la confirmation d'un **lien de procédure** ;
- Les correspondances partielles restent de simples pistes ;
- Un `TotalAmount` en EUR d'avis peut être montré uniquement lorsqu'il
  est non ambigu dans la publication, avec l'avertissement explicite que
  ce n'est ni un montant d'un lot individuel ni un paiement ;
- Résultats conservés dans une **base auxiliaire indépendante**
  `data/research/procurement-auto-concordance.sqlite` ;
- Aucune modification du gros fichier `public-procurement.sqlite`,
  aucune réimportation des DECP, aucune API privée ou installation de
  bibliothèque supplémentaire.

Exécuter, dans `apps/symfony` :

```bash
python3 tools/reconcile_procurement_boamp.py --contract-key cf6d3a11656665868dfd8f105f1a4d17edd5ec38
python3 tools/reconcile_procurement_boamp.py --limit 10 --offset 0 --pause 1.2
```

La correspondance exacte d'un avis à un **lot** et les paiements
**effectivement réglés** requièrent toujours des pièces financières
spécifiques : ce logiciel ne crée ni fausses preuves ni chiffres
comptables déduits d'un avis d'attribution.

## Déploiement sur une installation existante

Le changement touche des fichiers PHP/Twig/CSS, un nouveau script Python
facultatif et des tests. Aucune migration de la base nationale ni de la
base de comptes. Ne pas écraser les fichiers `data/`, `var/`,
`.env.local` ni les dépendances `vendor/`.

Guide : [PROCUREMENT_DOCUMENTARY_DOSSIERS.md](../apps/symfony/docs/PROCUREMENT_DOCUMENTARY_DOSSIERS.md).

## Validation

- Lint Twig/HTML et PHPUnit (sources financières, lots distincts) ;
- Tests hors ligne des identifiants BOAMP, références candidates et
  montants EUR uniques, sans requêtes réseau ;
- Tests Arch, HTTP et compilation Python ;
- Publication depuis `main` uniquement après les tests.
