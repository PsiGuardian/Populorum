# Populorum v0.13.1-clean — concordance BOAMP avec reprise automatique

La refonte des fiches documentaires publiée en v0.13.0-clean est
conservée intégralement, avec ses sources DECP, documents BOAMP,
preuves financières, historiques et absence de menus d'ancres vides.

## Amélioration

L'enrichissement officiel BOAMP peut maintenant fonctionner de manière
**récurrente, par petits lots**, sans retraiter les mêmes premières
fiches à chaque exécution :

```bash
python3 tools/reconcile_procurement_boamp.py --resume --limit 25 --pause 1.2
```

Le curseur de progression est conservé dans
`data/research/procurement-auto-concordance.sqlite`. Il repart au début
au terme d'un passage complet. Le script respecte la temporisation
entre appels et ne lance aucune recherche lors du chargement web.
Une tâche cron avec verrou `flock` peut le déclencher à faible fréquence.

Une erreur API est enregistrée sans effacer les références historiques.
Cette étape ne modifie jamais `public-procurement.sqlite`, les
contrats d'origine ni les montants déclarés. Les identités de lots et
les paiements demeurent non déduits.

## Installation existante

Pour une installation déjà en v0.13.0, seul
`tools/reconcile_procurement_boamp.py` change. Pour une installation
v0.12.x, utiliser tous les fichiers applicatifs de la refonte
v0.13.1 indiqués dans le guide documentaire.

Guide :
[PROCUREMENT_DOCUMENTARY_DOSSIERS.md](../apps/symfony/docs/PROCUREMENT_DOCUMENTARY_DOSSIERS.md).
