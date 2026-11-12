# Populorum v0.13.2-clean — correction du moteur de concordance BOAMP

## Incident corrigé

Le script `tools/reconcile_procurement_boamp.py` envoyait le nom de
champ `DONNEES` en majuscules dans la clause `select` de l'API
Opendatasoft v2.1. Le vrai identifiant technique est **`donnees`**
en minuscules ; l'API rejetait la recherche avec HTTP 400 :

`ODSQL query is malformed: Unknown field: DONNEES`.

Le correctif remplace uniquement ce sélecteur. Le nom affiché par certaines
interfaces de la source n'est pas le nom SQL utilisable dans l'API.

## Vérifications

- Requêtes de diagnostic HTTP réelles exécutées le 12 novembre 2026 :
  `select=...,DONNEES` retourne HTTP 400, `select=...,donnees`
  retourne HTTP 200.
- Test GitHub Actions sur l'API officielle avec le client corrigé :
  HTTP 200 et huit réponses BOAMP lues.
- Test hors ligne empêchant la régression du nom du champ.
- Validation Symfony et installation Arch.

## Mise à jour d'une installation existante

Seul `apps/symfony/tools/reconcile_procurement_boamp.py` est nécessaire
pour corriger les requêtes sur une installation v0.13.1. Le fichier
SQLite national n'est pas modifié, aucun import n'est à refaire.
Les échecs de recherches précédents pourront être réessayés avec :

```bash
python3 tools/reconcile_procurement_boamp.py \
  --contract-key cf6d3a11656665868dfd8f105f1a4d17edd5ec38
```

Une recherche HTTP 200 n'implique pas forcément un avis concordant :
la procédure est liée seulement si les identifiants source permettent
un rapprochement suffisamment fiable. Aucun paiement n'est inféré.
