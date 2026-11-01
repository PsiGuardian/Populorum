# Données géographiques, comptes et marchés publics

Cette application associe trois ensembles **distincts** de sources publiques : les communes INSEE et leurs codes postaux La Poste ; les comptes consolidés OFGL/DGFiP ; les données essentielles de la commande publique (DECP).

## Installation des références nationales

Depuis `apps/symfony`, exécuter :

```bash
python3 tools/install_reference_indexes.py
python3 tools/verify_postal_v7_2.py
```

L'archive `data/geography/communes-2026.sqlite.gz` contient un index public vérifié (millésime 2026, 34 875 communes et correspondances postales). L'installation le décompresse **seulement s'il est absent** ; elle ne remplace jamais un référentiel existant. Les données comptables DGFiP nécessaires aux trois villes pilotes sont déjà versionnées dans `data/research/`.

Pour mettre à jour le référentiel via les sources officielles, utiliser les outils `import_communes_insee.py` et `import_codes_postaux.py`. Les fichiers générés `*.sqlite` restent locaux ; seules les archives de référence validées sont versionnées.

## Importer les données DECP sans bloquer le site

Le code et les scripts sont versionnés. Les JSON nationaux volumineux, les journaux, les fichiers `.part` et les indexes SQLite DECP sont **locaux**, pas dans Git : certains JSON dépassent la limite de taille de GitHub et les publications mensuelles se recouvrent. Source officielle : https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides

```bash
# État des sources et des bases
python3 tools/decp_pipeline_v8.py status

# Récupération des ressources mensuelles absentes
python3 tools/decp_pipeline_v8.py download

# Construire / reprendre la base de travail à partir des sources téléchargées
python3 tools/decp_pipeline_v8.py import

# Contrôles : ces commandes nécessitent une base de travail déjà construite
python3 tools/decp_pipeline_v8.py verify
python3 tools/verify_decp_history_v8_1.py
```

En cas de **base de travail absente**, il est normal que `backfill_decp_history.py` et `verify_decp_history_v8_1.py` échouent : lancez d'abord `decp_pipeline_v8.py import`. Une base `public-procurement.sqlite` vide ne doit jamais être publiée. Le script `import` lit les sources JSON présentes et crée la base de travail en réutilisant le parseur `import_decp.py`. Il peut demander de nombreuses ressources processeur et disque pour l'ensemble des périodes.

Après validation **et arrêt réel de tous les processus utilisant la base** :

```bash
python3 tools/decp_pipeline_v8.py publish --site-stopped
```

Ne pas confondre montants contractuels, engagements budgétaires, montants d'avenants et paiements effectifs. La concordance DECP/OFGL est documentaire, pas une identité comptable. En particulier, un lieu d'exécution portant seulement un code département n'établit pas une dépense d'une commune précise.

## Exclusions de publication

Ne jamais ajouter `vendor/`, `var/`, `.env.local`, `.guardian-shield/`, les fichiers DECP bruts, les index DECP locaux, les fichiers de sauvegarde ou un dossier privé de secrets. Toute modification d'un dossier ignoré sur GitHub nécessite un import ou un déploiement distinct.
