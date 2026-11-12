# Populorum v0.12.5-clean — performance des dossiers documentaires DECP

Correctif ciblé de la v0.12.4-clean, conservant l'intégralité des
déclarations officielles, montants, historiques, lots et preuves financières.

## Correctifs

- Les dossiers interrogeaient les autres fiches de même référence avec
  `WHERE buyer_id=? AND kind=? AND original_id=?`. Les anciennes bases
  nationales n'ont **pas** d'index composé de ces trois clés. Chez les
  grands acheteurs, cela pouvait déclencher une recherche coûteuse.
- Pour une base ancienne non indexée, **ne plus exécuter la requête
  coûteuse à l'ouverture de la fiche** ; afficher une indication explicite
  plutôt que prétendre qu'aucune référence apparentée n'existe.
- Les nouvelles bases sont indexées pour
  `(buyer_id,kind,original_id,notice_date DESC,contract_key ASC)`.
  Cet index garantit une recherche ciblée et respecte l'ordre chronologique.
- Un ajout ponctuel de cet index dans une base existante est possible
  sans réimporter les données DECP. La lecture du registre national
  reste inchangée, aucun chiffre n'est modifié.
- Nouveau jalon de mesure `decp_related_references` pour diagnostiquer
  le coût du rapprochement sans exposer de données personnelles.
- Le signalement non bloquant des montants atypiques est présent
  aussi dans la fiche détaillée.

## Pour une installation existante

Seuls les fichiers `src/Service/PublicProcurementData.php` et
`templates/pages/markets.html.twig` sont nécessaires au **correctif
de délai**, sans migrations ni effacement du cache applicatif.
L'index de rapprochement des autres références est facultatif :
tant qu'il manque, le site n'interroge pas les contrats apparentés.

La commande ponctuelle d'indexation et ses précautions figurent dans
[le guide documentaire](../apps/symfony/docs/PROCUREMENT_DOCUMENTARY_DOSSIERS.md).

Les pages de recherche DECP nationales conservent leur mécanisme de
pagination et leurs index de tri en place.
