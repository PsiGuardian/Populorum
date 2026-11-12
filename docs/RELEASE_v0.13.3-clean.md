# Populorum v0.13.3-clean — recherche BOAMP plus pertinente

## Problème observé

Sur la base nationale DECP, les 25 premières recherches de la version
v0.13.2 ont renvoyé zéro concordance, alors que toutes les requêtes
API répondaient. Cela ne signifiait pas que les avis étaient absents.

Le programme envoyait une recherche générique sur les mots de l'objet
sans filtrer d'abord l'acheteur. Pour des intitulés comme « Fourniture
de dispositifs médicaux », plusieurs milliers d'avis existent, alors
que le script ne lisait que huit premières réponses.

En parallèle, les références DECP uniquement numériques étaient
rejetées par le moteur de classement.

## Correctif

- Recherche officielle ODSQL filtrée par **SIRET cité dans le corps de
  l'avis** et par **mot distinctif de l'objet**.
- Lecture jusqu'à trente avis filtrés par interrogation ; affichage du
  nombre d'avis effectivement examinés.
- Acceptation des identifiants DECP numériques sans les traiter comme
  preuves certaines : les suites courtes peuvent aussi représenter
  des montants ou d'autres références.
- Les intitulés proches restent des **pistes documentaires**, même
  lorsque le SIRET est présent. La double concordance
  SIRET + référence distinctive permet un lien de procédure, jamais
  une preuve par lot ni un paiement.
- Protection par période pour les rapprochements fondés uniquement
  sur la similarité des objets.
- Maintien de l'import incrémental `--resume` et de la lecture
  seule des données nationales.

## Contrôle d'une installation existante

Seul `apps/symfony/tools/reconcile_procurement_boamp.py` est à
remplacer pour bénéficier de cette correction depuis v0.13.2.

Pour recalculer le premier lot sans modifier le curseur de reprise :

```bash
python3 tools/reconcile_procurement_boamp.py --limit 25 --offset 0 --pause 1.5
```

Pour une fiche :

```bash
python3 tools/reconcile_procurement_boamp.py \
  --contract-key cf6d3a11656665868dfd8f105f1a4d17edd5ec38
```

**Limite** : la présence d'un SIRET dans un avis n'établit pas à elle
seule le rôle d'acheteur ni l'identité du lot. La base BOAMP et la
base DECP ne partagent pas toujours le même identifiant de procédure.
Un rapprochement national complet exige l'import des exports
officiels et une consolidation documentaire locale. Aucun montant
DECP ou paiement n'est ajouté ou corrigé automatiquement.
