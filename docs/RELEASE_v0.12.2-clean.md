# Populorum v0.12.2-clean — performances DECP nationales

Cette version conserve le principe **installation neuve exclusivement** :
le logiciel est livré en sources propres, sans base SQLite nationale, données
personnelles, secrets, dépendances vendor ni scripts de migration. Suivre
[LIRE_AVANT_INSTALLATION.md](../LIRE_AVANT_INSTALLATION.md).

## Correction du goulet d'étranglement des marchés publics

Mesures de référence (11 novembre 2026, base DECP v2 ~3,2 Go) :

- Page d'accueil : 4,868 s HTTP (54,2 ms de traitement PHP mesuré) ;
- Page marchés France entière : **172,018 s** (171 891 ms de `page_data`) ;
- Page marchés commune 76351 : 0,375 s ;
- SQLite montrait `USE TEMP B-TREE FOR ORDER BY` sur la recherche communale.

Correctifs :

1. Index des tris `notice_date DESC,contract_key ASC`, pour France entière,
   commune/département et marché/concession.
2. Réutilisation du total national déjà en cache pour les listes sans filtre
   ou avec uniquement le filtre marché/concession, supprimant un COUNT national
   redondant.
3. Traces `Server-Timing` détaillées : `decp_coverage`, `decp_count`,
   `decp_rows`, `decp_search`, `decp_detail`.
4. Test automatisé du plan SQLite sans tri temporaire sur les principaux
   parcours de liste.

La base nationale et ses documents originaux ne sont pas modifiés
par le logiciel en consultation. Les index sont construits automatiquement
lors de l'**import initial** des fichiers officiels.

## Base nationale existante

Le projet n'installe et ne migre aucune ancienne base. Cependant, pour
diagnostiquer une base DECP v2 déjà publiée sur un poste local, les index
secondaires peuvent être créés en place, **sans reconstruire les 3,2 Go**.
La procédure documentée dans
[PERFORMANCE.md](PERFORMANCE.md) exige l'arrêt préalable du serveur,
la vérification de l'espace disque et le contrôle du plan SQL après création.

## Sources et limites

Sources publiques DECP : https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides

L'optimisation est vérifiée sur des données synthétiques en CI. Elle ne garantit
aucun temps de réponse chiffré sur un stockage local différent. Les recherches
FTS et les tris par montant peuvent nécessiter des mesures et des index propres.
