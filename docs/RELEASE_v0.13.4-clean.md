# Populorum v0.13.4-clean — audit contradictoire des avis BOAMP

## Pourquoi cet audit ?

Sur 25 marchés éligibles, la recherche BOAMP améliorée a retenu
16 pistes documentaires et 1 concordance d'identifiants. Pour les
17 autres fiches, aucun avis n'a été retenu. **Cela ne prouve pas
qu'aucun avis lié n'existe** : certains termes sont insuffisants,
certains avis ne mentionnent pas la même référence ou les résultats
peuvent être tronqués.

## Nouvel outil documentaire (lecture seule)

`tools/audit_procurement_concordance.py` :
- récapitule les références, objets, acheteurs, dates et montants DECP ;
- présente l'historique des rapprochements et les liens BOAMP ;
- signale les fiches sans lien comme **indéterminées** et non négatives ;
- peut lancer des **contre-recherches** BOAMP bornées par
  SIRET + référence ou SIRET + mot distinctif, et indique si
  des résultats supplémentaires existent ;
- n'écrit ni dans la base DECP, ni dans le registre de concordance,
  ni dans le curseur de progression.

Depuis `apps/symfony` :

```bash
python3 tools/audit_procurement_concordance.py --limit 25 --offset 0 \
  --output var/boamp-audit-25.txt

python3 tools/audit_procurement_concordance.py --limit 25 --offset 0 \
  --online --output var/boamp-audit-25-online.txt
```

Les recherches complémentaires ne démontrent pas l'identité du
lot ni un paiement ; elles servent à **identifier des documents
à vérifier manuellement**.

La version v0.13.3 reste inchangée pour l'enrichissement normal.
Aucune migration, aucun nouveau téléchargement DECP, aucune
modification des comptes, des votes ou des montants.
