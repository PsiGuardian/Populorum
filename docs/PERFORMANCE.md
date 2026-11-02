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

## Optimisations appliquées

- Les recherches des codes postaux utilisent l'index SQLite `postal_search` par plage bornée, plutôt que parcourir toutes les communes avec `LIKE` et une sous-requête corrélée. Une saisie de cinq chiffres conserve une recherche **exacte** ; une saisie de deux à quatre chiffres conserve le **préfixe strict**.
- Les listes de régions et l'inventaire des totaux DECP et des indicateurs SSMSI peuvent utiliser le cache public Symfony `cache.app`, avec une clé dérivée des métadonnées du fichier SQLite. Aucun profil, vote, identifiant de compte ou autre état de visiteur n'est mis en cache.
- Les pages nationales restent paginées côté SQLite ; les résultats complets des communes et des marchés ne sont pas chargés en mémoire PHP.

## Objectifs et contrôles

Comparer les médianes `HTTP` entre deux exécutions dans un environnement **identique**, avec les mêmes bases. Les chiffres mesurés sur le petit référentiel INSEE ne doivent pas être extrapolés à la base DECP de plusieurs Go. Conserver le contrôle fonctionnel des recherches exactes, des préfixes postaux, des données non diffusées et des sources publiques après chaque optimisation.
