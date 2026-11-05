# Populorum v0.12.1-clean — première installation uniquement

Cette version alpha est conçue pour un **déploiement neuf**, dans un dossier
vide et avec des bases SQLite nouvellement créées. Elle n'est pas un correctif
à appliquer sur un ancien répertoire et ne contient aucune migration des
données nationales DECP déjà publiées.

## Installation

Suivre exclusivement le guide
[LIRE_AVANT_INSTALLATION.md](https://github.com/PsiGuardian/Populorum/blob/main/LIRE_AVANT_INSTALLATION.md).

Le logiciel est livré en archive de sources, sans `vendor/`, secrets locaux,
bases privées, données citoyennes ni fichiers JSON nationaux volumineux.

Dans une installation neuve, les étapes principales sont :

```bash
cd apps/symfony
composer install --no-interaction --prefer-dist --no-progress
php bin/console doctrine:migrations:migrate --no-interaction --env=demo
php bin/console app:geography:install --env=demo
php bin/console app:demo:prepare --env=demo
```

La création préalable des clés locales et les vérifications PHP sont décrites
dans le guide unique : ne pas lancer ces commandes partielles à la place
du guide complet.

## Données des marchés publics

Le format initial est directement `populorum-decp-2`. La base nationale est
créée à partir des ressources JSON officielles de
[data.gouv.fr](https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides).
L'ancien index DECP v1 et les anciennes archives de base nationale ne sont
pas acceptés.

```bash
python3 tools/decp_pipeline.py download
python3 tools/decp_pipeline.py import
python3 tools/decp_pipeline.py verify
# Seulement après arrêt de Symfony et de tous les lecteurs SQLite :
python3 tools/decp_pipeline.py publish --site-stopped
python3 tools/decp_pipeline.py status
```

Le pipeline vérifie les sources et leur provenance, conserve les déclarations
complètes et exige la validation de l'index avant sa première publication.
Il **refuse** une destination déjà existante : aucune copie d'une ancienne
base, migration, fusion ni suppression automatique n'est prévue.

Les justificatifs documentaires affichent leur titre, leur source et un lien
précis ; toute information manquante ou correspondance documentaire incertaine
est explicitement signalée. Un montant contractuel ne prouve pas un paiement.

## Documentation multilingue

Les 28 documents `apps/symfony/docs/translations/*/INSTALLATION.md` ont été
recentrés sur les prérequis, la première installation et le dépannage,
sans procédure de mise à jour des anciennes versions.

## Contrôles et limites

La CI teste l'installation Symfony vierge et l'import DECP v2 sur des
données synthétiques, le refus de remplacer un index public préexistant,
la traçabilité des archives, le rendu et les contrôles de sécurité.

L'import complet des données nationales doit être réalisé séparément sur
la machine de destination ; cette release ne comporte pas une base nationale
préremplie.

Références :
- Guide DECP v2 : `apps/symfony/docs/PROCUREMENT_EVIDENCE_V2.md`
- Fichiers DECP officiels : https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides
