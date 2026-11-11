# Performance des pages Populorum

Ce document décrit un protocole reproductible **sans fichier ni script temporaire**. Les mesures sur l'ordinateur du visiteur sont différentes des mesures CI GitHub : CPU, stockage SQLite, réseau et taille des données peuvent changer les résultats.

## Relevé de chaque page dans la CI

Le test permanent `apps/symfony/tests/http_smoke.py` démarre Symfony en environnement `demo` sur une copie SQLite temporaire, relève un passage de chauffe, puis deux requêtes HTTP mesurées pour **21 chemins** (accueil, territoires, chemins, consultations, expériences, convergence, mémoire, transparence, infrastructure, gouvernance, participation, charte, récits, services, budget, devis, communes, marchés et trois filtres). Il affiche dans les logs GitHub Actions un inventaire trié par durée HTTP médiane.

Avec `POPULORUM_TIMING=1`, Populorum expose également un en-tête `Server-Timing` qui décompose les durées serveur : lecture du scénario, décodage JSON, état public, données de page, convergence et rendu Twig. Ce relevé n'inclut pas les temps réseau réels des visiteurs.

**Attention :** la CI ne télécharge pas la base DECP nationale complète (archive compressée d'environ 464 Mio). Les temps de recherche sur l'index national doivent être mesurés séparément sur le serveur qui dispose effectivement de ces données.

## Relevé sur un serveur existant

Dans le terminal Arch Linux, depuis un serveur Populorum déjà lancé :

```bash
curl -sS -o /dev/null -w 'Accueil: %{time_total} s — code %{http_code}\n' http://127.0.0.1:5412/
curl -sS -o /dev/null -w 'Convergence: %{time_total} s — code %{http_code}\n' http://127.0.0.1:5412/convergence
curl -sS -o /dev/null -w 'Communes: %{time_total} s — code %{http_code}\n' http://127.0.0.1:5412/communes/q/66300
curl -sS -o /dev/null -w 'Marchés: %{time_total} s — code %{http_code}\n' http://127.0.0.1:5412/marches-publics/q/web
curl -sS -o /dev/null -w 'Budget: %{time_total} s — code %{http_code}\n' http://127.0.0.1:5412/budget-et-priorites
```

Effectuer plusieurs passages après la mise en route. Sur la machine locale, définir `POPULORUM_TIMING=1` dans l'environnement du serveur PHP **avant son démarrage** pour exposer les détails, puis consulter `curl -sSI http://127.0.0.1:5412/convergence` (si la route accepte HEAD) ou `curl -sS -D - -o /dev/null http://127.0.0.1:5412/convergence`.

## Audit DECP national — 11 novembre 2026

Observations sur un poste Arch Linux avec index DECP d'environ 3,2 Go :

| Requête | HTTP total | `Server-Timing page_data` |
| --- | ---: | ---: |
| Accueil | 4,868 s | 0,52 ms |
| Marchés, France entière | 172,018 s | 171 891 ms |
| Marchés, commune 76351 | 0,375 s | 281,5 ms |

Le ralentissement national vient bien du traitement des données de la page,
pas des fichiers statiques. Le délai supplémentaire constaté sur l'accueil
n'est pas attribué à son contrôleur : un serveur PHP intégré à un seul
processus peut faire attendre les nouvelles requêtes lorsqu'une recherche
DECP est encore en cours.

Le plan SQLite local de la requête communale présentait
`USE TEMP B-TREE FOR ORDER BY`, car l'index géographique ne couvrait pas
l'ordre date décroissante + identifiant croissant.

L'importeur des installations neuves ajoute maintenant :
- `contracts_date_desc` sur `(notice_date DESC,contract_key ASC)` ;
- `contracts_geo_date_desc` sur `(location_type,location_code,notice_date DESC,contract_key ASC)` ;
- `contracts_kind_date_desc` sur `(kind,notice_date DESC,contract_key ASC)`.

La recherche utilise les totaux publics déjà mis en cache lorsqu'elle n'a
aucun filtre ou seulement un filtre de nature. Les autres comptages sont
conservés exacts. Le traçage ajoute `decp_coverage`, `decp_count`,
`decp_rows`, `decp_search` et `decp_detail`.

### Optimiser un index national v2 déjà construit, sans réimporter

Ce n'est **pas une migration de version** : seules des structures d'index
secondaires sont créées sur la base DECP v2 existante. Les déclarations
originales et leurs empreintes ne sont pas modifiées.

1. Arrêter Symfony, tout autre processus qui lit le fichier DECP et
   vérifier que le disque dispose d'espace libre : `df -h .`.
2. Depuis `apps/symfony`, lancer le bloc suivant. Sur une base nationale
   de plusieurs Go, la création initiale peut générer des E/S importantes.

```bash
sqlite3 data/research/public-procurement.sqlite <<'SQL'
PRAGMA busy_timeout=5000;
CREATE INDEX IF NOT EXISTS contracts_date_desc
  ON contracts(notice_date DESC,contract_key ASC);
CREATE INDEX IF NOT EXISTS contracts_geo_date_desc
  ON contracts(location_type,location_code,notice_date DESC,contract_key ASC);
CREATE INDEX IF NOT EXISTS contracts_kind_date_desc
  ON contracts(kind,notice_date DESC,contract_key ASC);
PRAGMA optimize;
SQL
```

3. **Vérifier le plan réel**. Aucun parcours n'est censé requérir un tri
   temporaire pour la liste nationale par date et le filtre communal :

```bash
sqlite3 -readonly data/research/public-procurement.sqlite "
EXPLAIN QUERY PLAN
SELECT contract_key FROM contracts
ORDER BY notice_date DESC,contract_key ASC LIMIT 30;
EXPLAIN QUERY PLAN
SELECT contract_key FROM contracts
WHERE location_type='Code commune' AND location_code='76351'
ORDER BY notice_date DESC,contract_key ASC LIMIT 30;
"
```

4. Redémarrer avec le traceur, puis mesurer plusieurs fois les pages :

```bash
POPULORUM_TIMING=1 APP_ENV=demo APP_DEBUG=0 php -S 127.0.0.1:5412 -t public
```

Dans un autre terminal :

```bash
curl -sS -D - -o /dev/null -w 'Marchés France : %{time_total}s\n' http://127.0.0.1:5412/marches-publics
curl -sS -D - -o /dev/null -w 'Marchés Le Havre : %{time_total}s\n' http://127.0.0.1:5412/marches-publics/city/76351
```

La performance finale dépend aussi du stockage et de la charge du serveur :
l'indexation élimine des tris évitables mais ne garantit pas une durée HTTP
chiffrée. Les recherches par fournisseur, texte libre ou tri par montant
peuvent suivre d'autres plans et devront être mesurées séparément.

## Optimisations appliquées

- Les recherches des codes postaux utilisent l'index SQLite `postal_search` par plage bornée, plutôt que parcourir toutes les communes avec `LIKE` et une sous-requête corrélée. Une saisie de cinq chiffres conserve une recherche **exacte** ; une saisie de deux à quatre chiffres conserve le **préfixe strict**.
- Les listes de régions et l'inventaire des totaux DECP et des indicateurs SSMSI peuvent utiliser le cache public Symfony `cache.app`, avec une clé dérivée des métadonnées du fichier SQLite. Aucun profil, vote, identifiant de compte ou autre état de visiteur n'est mis en cache.
- Les 28 chartes publiques sont mises en cache en HTML après le premier rendu Markdown. La clé dépend du fichier de l'archive et du code langue ; une modification de l'archive invalide les entrées. La charte n'utilise pas de cache partagé avec des contenus de visiteurs.
- Les pages nationales restent paginées côté SQLite ; les résultats complets des communes et des marchés ne sont pas chargés en mémoire PHP.

## Objectifs et contrôles

Comparer les médianes `HTTP` entre deux exécutions dans un environnement **identique**, avec les mêmes bases. Les chiffres mesurés sur le petit référentiel INSEE ne doivent pas être extrapolés à la base DECP de plusieurs Go. Conserver le contrôle fonctionnel des recherches exactes, des préfixes postaux, des données non diffusées et des sources publiques après chaque optimisation.
