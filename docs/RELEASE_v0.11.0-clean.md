# Populorum v0.11.0-clean — dossiers officiels et délibération causale

Publication d'une **nouvelle archive installable et autonome**, construite à
partir du commit validé par CI. Le système fonctionne sans anciennes bases
SQLite de catalogue, sans cache de rapports, sans scripts temporaires et sans
répertoire `vendor/` inclus dans l'archive.

## Changements depuis v0.10.1-clean

- Pages 2 « Propositions de loi » et 3 « Consultations » reconstruites autour
  des dossiers législatifs officiels du Sénat ; filtres, pagination et suivi
  des scrutins de simulation.
- Auteurs, dates de dépôt, références et sources parlementaires **publics
  avant tout vote** ; distinction entre statut du texte et vote expérimental.
- Bibliothèque documentaire liée à chaque dossier : exposé des motifs,
  textes, commissions, rapports précis, amendements, débats et navette
  parlementaire, seulement lorsque les liens officiels sont présents.
- Arbre de délibération après le vote : chaque publication associe une
  cause et une conséquence, leurs degrés de preuve et des références.
- Révisions historisées des branches causales : textes et sources de toutes
  les versions restent lisibles ; modification réservée à l'auteur actif.
- Liens vers les communes réelles corrigés (plus d'erreur avec `c13068`).
- Indexation stricte des rapports sénatoriaux : exclusion des menus
  « Derniers rapports » / « Tous les rapports » et des recherches globales.
- Suppression de la commande `--clean-cache` : **une installation neuve
  construit un catalogue sain directement à partir des sources officielles**.
- Préservation du système existant de référentiels INSEE, DECP et SSMSI.

## Installation et données

Télécharger **`Populorum-v0.11.0-clean.tar.gz`** et
**`SHA256SUMS.txt`**, vérifier leur empreinte, puis suivre
[`LIRE_AVANT_INSTALLATION.md`](https://github.com/PsiGuardian/Populorum/blob/main/LIRE_AVANT_INSTALLATION.md).

La release livre uniquement le logiciel et les petites ressources
reproductibles versionnées. Les données parlementaires s'importent ensuite
depuis le CSV du Sénat et les pages des dossiers :

```bash
python3 tools/import_policy_catalog.py \
  --senat-csv /tmp/populorum-propositions-senat.csv --limit 10000 --days 90
python3 tools/enrich_senate_dossiers.py \
  --limit 2000 --offset 0 --pause 0.6 --timeout 20
```

L'URL du CSV et les étapes préalables sont dans le guide d'installation.
La base personnelle `var/policy-catalog.sqlite` **n'est pas publiée**.
La release ne réinitialise pas les votes des installations existantes :
une mise à jour doit conserver et sauvegarder leurs bases privées.
Ne jamais effacer un historique de contributions pour « repartir de zéro »
sur une installation contenant déjà des données de citoyens.

## Intégrité et limites

L'archive est créée par GitHub Actions après validation de `native-demo`
et `arch-install`. La release joint un manifeste SHA-256 indépendant de
la base nationale DECP, qui reste disponible séparément.

Les textes, auteurs, rapports et extraits sont des sources parlementaires
publiques ; une justification d'auteur ne vaut pas preuve scientifique.
Les scrutins Populorum sont fictifs, non représentatifs et n'ont aucune
valeur juridique.

Documentation technique : [`apps/symfony/docs/POLICY_CATALOG.md`](https://github.com/PsiGuardian/Populorum/blob/main/apps/symfony/docs/POLICY_CATALOG.md).
