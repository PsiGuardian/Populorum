# Populorum v0.12.6-clean — sécurité des fiches DECP pendant une mise à jour partielle

Correctif de compatibilité du template `templates/pages/markets.html.twig`
avec les services déjà chargés dans PHP-FPM, OPcache ou un processus PHP
lorsqu'une mise à jour de fichiers est faite sans redémarrer ce processus.

## Correction
- Les indicateurs documentaires facultatifs `amount_warnings` ont une
  valeur par défaut vide dans Twig, aussi bien en détail que dans la liste.
- L'indicateur `reference_relations.indexed` est examiné uniquement
  s'il est défini : pas de nouvelle erreur Twig pendant le déploiement
  d'un service v0.12.4 avec le template v0.12.5.
- Aucun montant, contrat, publication, compte utilisateur ni base
  SQLite n'est modifié.
- La version v0.12.5 inclut déjà l'affectation PHP de
  `amount_warnings` dans `PublicProcurementData::detail()`.
  Si l'erreur apparaît avec la bonne source PHP sur disque,
  redémarrer le processus serveur concerné pour purger son OPcache,
  puis vérifier le rendu.

## Correctif d'une installation existante
Un seul fichier applicatif à mettre à jour :
`apps/symfony/templates/pages/markets.html.twig`.
Il n'y a aucune migration DECP ni import de données.

Pour une mise à jour en place, vérifier aussi que
`src/Service/PublicProcurementData.php` est bien la version v0.12.5
ou ultérieure et que les processus PHP ont été redémarrés.

Cette modification ne corrige pas à elle seule les ralentissements
des fiches ; ceux-ci sont traités par l'indexation et le garde-fou de
v0.12.5-clean.
