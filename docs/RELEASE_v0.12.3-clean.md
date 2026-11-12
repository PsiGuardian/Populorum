# Populorum v0.12.3-clean — sélection territoriale indépendante du compte

Archive de première installation propre, bâtie sur la version DECP v2
optimisée et les données publiques sourcées. Elle est destinée à une
**installation neuve** dans un répertoire vide, sans migration d'anciennes
bases SQLite et sans reprise automatique d'une installation antérieure.

## Correction

Le code rétablissait automatiquement la commune de résidence d'un compte
de test quand l'utilisateur choisissait la France entière. Désormais :
- Passa peut rester la commune du compte de test ;
- le périmètre choisi est conservé dans la session de navigation ;
- « Revenir à la France » efface seulement cette sélection et affiche
  la France entière, sans modifier le profil ni ses votes.

Un test HTTP valide le cycle complet : profil de test domicilié à Passa,
sélection explicite de Passa, retour à la France entière, maintien du profil.

## Documentation

Le guide [LIRE_AVANT_INSTALLATION.md](../LIRE_AVANT_INSTALLATION.md) ne
mentionne plus une ancienne erreur de copier-coller. Les commandes DECP
et l'installation facultative SSMSI restent présentées dans des sections
indépendantes, sans avertissement technique hors sujet.

## Données

Aucune modification du format des données, aucune migration, aucun effacement
et aucun réimport DECP n'est requis pour cette correction.
Le jeu de données national DECP demeure téléchargeable depuis
https://www.data.gouv.fr/datasets/donnees-essentielles-de-la-commande-publique-fichiers-consolides

Pour installer la source propre :
https://github.com/PsiGuardian/Populorum/blob/main/LIRE_AVANT_INSTALLATION.md
