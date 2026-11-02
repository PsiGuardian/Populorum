# Référentiels nationaux de Populorum

**La procédure d'installation complète, en une seule page**, avec toutes les commandes de téléchargement INSEE, marchés publics DECP et statistiques SSMSI, figure dans [LIRE_AVANT_INSTALLATION.md](https://github.com/PsiGuardian/Populorum/blob/main/LIRE_AVANT_INSTALLATION.md).

Les archives officielles ont été publiées sur [data-national-2026](https://github.com/PsiGuardian/Populorum/releases/tag/data-national-2026). Leur installation est facultative en tant que téléchargement séparé : le référentiel INSEE est déjà inclus dans les sources du site sous forme compressée, tandis que les bases DECP et SSMSI enrichissent les pages sans être nécessaires au fonctionnement du moteur de simulation.

Les bases externes sont conservées dans `data/geography/` et `data/research/`, séparément du fichier des scénarios `var/populorum.sqlite`. **Ne pas écraser une base existante ni perdre les données locales.** Les montants DECP sont contractuels, non des paiements attestés, et une statistique indisponible ne constitue jamais un zéro.

Ce document ne duplique plus les commandes, afin d'éviter des instructions divergentes d'une version à l'autre.
