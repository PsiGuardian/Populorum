# Populorum v0.12.0-clean — Observatoire national des marchés publics DECP v2

Nouvelle archive de sources propres, produite à partir d'un commit validé par
GitHub Actions. Aucun index national, jeu de JSON de plusieurs Go, secret,
cache de travail ni répertoire `vendor/` n'est inclus dans l'archive.

## Changement de format alpha

**L'index DECP v1 n'est plus compatible**. Aucune migration applicative
de compatibilité n'est fournie : l'index est reconstruit depuis les fichiers
officiels de [data.gouv.fr](https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides).
L'ancienne archive `data-national-2026/public-procurement.sqlite.tar.gz`
contient un schéma v1 ; elle ne doit pas être installée pour cette version.

Le moteur conserve les déclarations originales, leurs fichiers et SHA-256.
Un marché affiché n'est ni un paiement comptable ni une évaluation du résultat.
Une modification publiée n'est pas automatiquement une augmentation nette.

## Nouveautés

- Séparation prudente des lots partageant un identifiant de procédure et un CPV.
- Enregistrement intégral des déclarations DECP, compression et empreintes SHA-256.
- Reconstruction v2 dans une base de travail indépendante, validation des
  déclarations manquantes et publication contrôlée après arrêt des lecteurs.
- Manifestes des URL officielles et de leurs révisions : un fichier modifié
  peut être actualisé même si sa taille reste identique.
- Provenance et lien vers le fichier ou catalogue officiel affichés dans
  chaque résultat et dans chaque fiche détaillée, avec provenance précise
  de la déclaration présentée.
- Registre documentaire sourcé : motifs, besoin, prestations, sélection,
  financement, paiements et résultats, uniquement lorsqu'une pièce exacte
  est disponible. Les correspondances BOAMP restent candidates tant qu'elles
  ne sont pas confirmées.
- Première étude sourcée : relance du lot 6 de l'école de Vimartin-sur-Orthe,
  selon le procès-verbal du 20 février 2023 publié en republication.

## Installation

Suivre [LIRE_AVANT_INSTALLATION.md](https://github.com/PsiGuardian/Populorum/blob/main/LIRE_AVANT_INSTALLATION.md).
Pour les données DECP :

```bash
cd apps/symfony
python3 tools/decp_pipeline.py download
python3 tools/decp_pipeline.py import
python3 tools/decp_pipeline.py verify
# Une fois Symfony et les lecteurs SQLite arrêtés :
python3 tools/decp_pipeline.py publish --site-stopped
python3 tools/decp_pipeline.py status
```

Le téléchargement national est indépendant du dépôt Git. Le schéma
`populorum-decp-2` est requis pour l'observatoire. L'index personnel des
citoyens et des scrutins n'est pas concerné et ne doit pas être effacé.

## Références

- Source DECP officielle : https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides
- API BOAMP (DILA) : https://www.boamp.fr/pages/donnees-ouvertes-et-api/
- Guide : `apps/symfony/docs/PROCUREMENT_EVIDENCE_V2.md`

**Limite :** les documents détaillés ne sont pas tous accessibles en open data,
et l'enrichissement documentaire reste partiel tant que les pièces précises ne
sont pas récupérées et vérifiées. Aucun bénéfice, motivation ou paiement n'est
déduit du seul montant du contrat.
